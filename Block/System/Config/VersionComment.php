<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Block\System\Config;

use Iwoca\Iwocapay\Model\Version;
use Magento\Config\Model\Config\CommentInterface;

/**
 * Renders the module version in the admin payment config from the single
 * source of truth (Version), so the displayed version always matches what's
 * sent to the iwocaPay API rather than a hardcoded copy in system.xml.
 */
class VersionComment implements CommentInterface
{
    private Version $version;

    public function __construct(Version $version)
    {
        $this->version = $version;
    }

    /**
     * @param string $elementValue
     * @return string
     */
    public function getCommentText($elementValue)
    {
        return $this->version->get();
    }
}
