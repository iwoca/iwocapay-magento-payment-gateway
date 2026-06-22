<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Plugin\Config;

use Iwoca\Iwocapay\Model\Config as IwocapayConfig;
use Iwoca\Iwocapay\Model\CredentialValidator;
use Magento\Config\Model\Config as AdminConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;

/**
 * Verifies the iwocaPay credentials against the connection_check endpoint
 * *before* the admin configuration is saved, and blocks the whole save if
 * verification fails.
 *
 * These credentials are used to create real customer transactions, so we must
 * not persist an unverified token - a seller going live with a bad token would
 * only find out when their customers fail to place an order at checkout.
 *
 * This is the Magento equivalent of the WooCommerce plugin overriding
 * process_admin_options() (see connection-check.php there): verification runs
 * only when a credential-related field changed (Seller Access Token, Seller ID,
 * or Mode), so editing unrelated settings - title, banners, etc. - saves with
 * no API call and an iwoca outage can't block those edits. When it does run,
 * any failure (bad credentials or a transient/unreachable API) aborts the save
 * by throwing a LocalizedException, which the System Config Save controller
 * catches: it shows the message and redirects back to the edit page (the failed
 * save persisted nothing, so the form shows the still-stored values).
 *
 * Note: Magento has a single credential set and a Mode (Staging/Production)
 * dropdown, rather than WooCommerce's two environments behind a Sandbox toggle,
 * so there is no "verify the inactive environment later" case - there is only
 * ever one set of credentials to verify, against the base URL the chosen Mode
 * selects.
 */
class ValidateCredentialsOnSave
{
    /**
     * Group/field ids in system.xml. The credential fields live in the nested
     * `iwocapay_required` group under the `iwocapay` group, but we search the
     * posted structure by field id so we don't depend on that nesting.
     */
    private const FIELD_SELLER_ACCESS_TOKEN = 'seller_access_token';
    private const FIELD_SELLER_ID = 'seller_id';
    private const FIELD_MODE = 'mode';

    private const IWOCAPAY_SECTION = 'payment';
    private const IWOCAPAY_GROUP = 'iwocapay';

    private CredentialValidator $credentialValidator;
    private IwocapayConfig $iwocapayConfig;
    private ManagerInterface $messageManager;

    public function __construct(
        CredentialValidator $credentialValidator,
        IwocapayConfig $iwocapayConfig,
        ManagerInterface $messageManager
    ) {
        $this->credentialValidator = $credentialValidator;
        $this->iwocapayConfig = $iwocapayConfig;
        $this->messageManager = $messageManager;
    }

    /**
     * @param AdminConfig $subject
     * @return void
     * @throws LocalizedException
     */
    public function beforeSave(AdminConfig $subject): void
    {
        // Only act on saves of the iwocaPay group within the payment section.
        if ($subject->getSection() !== self::IWOCAPAY_SECTION) {
            return;
        }

        $groups = (array)$subject->getGroups();
        if (!isset($groups[self::IWOCAPAY_GROUP])) {
            return;
        }

        $postedToken = $this->findPostedValue($groups, self::FIELD_SELLER_ACCESS_TOKEN);
        $postedSellerId = $this->findPostedValue($groups, self::FIELD_SELLER_ID);
        $postedMode = $this->findPostedValue($groups, self::FIELD_MODE);

        // None of the three credential fields were part of this save (e.g. only
        // banners changed): nothing to verify.
        if (null === $postedToken && null === $postedSellerId && null === $postedMode) {
            return;
        }

        $storedToken = $this->iwocapayConfig->getSellerAccessToken();
        $storedSellerId = $this->iwocapayConfig->getSellerId();
        $storedMode = $this->iwocapayConfig->getMode();

        // An obscured token (all asterisks) means the field was left untouched,
        // so the stored token still applies. This mirrors the Encrypted backend
        // model, which skips saving an all-asterisk value for the same reason.
        $token = $this->resolveToken($postedToken, $storedToken);
        $sellerId = null !== $postedSellerId ? trim((string)$postedSellerId) : $storedSellerId;
        $mode = null !== $postedMode ? (int)$postedMode : $storedMode;

        if (!$this->credentialsChanged($token, $sellerId, $mode, $storedToken, $storedSellerId, $storedMode)) {
            return;
        }

        $result = $this->credentialValidator->evaluate($sellerId, $token, $mode);

        if ('error' === $result['type']) {
            // Aborting the save here means an unverified token is never
            // persisted. The Save controller catches this, shows the message,
            // and redirects back to the edit page.
            throw new LocalizedException(__($result['text']));
        }

        $this->messageManager->addSuccessMessage($result['text']);
    }

    /**
     * Whether the effective credentials differ from what's stored. Editing an
     * unrelated setting must not trigger an API call, so we only verify when
     * the token, seller id, or mode actually changed.
     */
    private function credentialsChanged(
        string $token,
        string $sellerId,
        int $mode,
        string $storedToken,
        string $storedSellerId,
        int $storedMode
    ): bool {
        return $token !== $storedToken
            || $sellerId !== $storedSellerId
            || $mode !== $storedMode;
    }

    /**
     * Resolve the token to validate. An obscured (all-asterisk) value means the
     * field was left untouched, so the stored token applies; an empty value
     * means the seller cleared it and is returned as-is so it fails validation.
     */
    private function resolveToken(?string $postedToken, string $storedToken): string
    {
        if (null === $postedToken) {
            return $storedToken;
        }

        $postedToken = trim($postedToken);

        if (preg_match('/^\*+$/', $postedToken)) {
            return $storedToken;
        }

        return $postedToken;
    }

    /**
     * Recursively find a posted field value by its field id within the groups
     * structure. Returns null if the field wasn't part of this save.
     *
     * The posted structure follows system.xml nesting:
     * groups[iwocapay][groups][iwocapay_required][fields][seller_id][value].
     *
     * @param array $node
     * @param string $fieldId
     * @return string|null
     */
    private function findPostedValue(array $node, string $fieldId): ?string
    {
        if (isset($node['fields'][$fieldId]) && array_key_exists('value', $node['fields'][$fieldId])) {
            return (string)$node['fields'][$fieldId]['value'];
        }

        // Descend into any array child. The posted structure interleaves
        // group-id keys (e.g. 'iwocapay', 'iwocapay_required') with the
        // 'groups'/'fields' wrappers, so we can't rely on fixed key names.
        foreach ($node as $child) {
            if (is_array($child)) {
                $found = $this->findPostedValue($child, $fieldId);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
