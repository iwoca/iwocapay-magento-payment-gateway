<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

/**
 * Single source of truth for the module's integration version.
 *
 * The VERSION constant below is the ONLY place that carries the release
 * number. CI substitutes the 2.3.3 placeholder at publish time (see
 * .gitlab-ci.yml, which runs envsubst over every .php/.xml file). Everything
 * that needs the version at runtime - request headers, connection checks,
 * integration events, the admin display - injects this class and calls get()
 * rather than embedding its own copy of the placeholder. That way a new
 * placeholder can never ship un-substituted from a file CI happened not to
 * envsubst, and bumping the version is a one-line change.
 *
 * Raw clones (local dev) must stamp this one place with a real version, or the
 * iwocaPay API rejects requests asking you to update your plugin.
 */
class Version
{
    private const VERSION = '2.3.3';

    public function get(): string
    {
        return self::VERSION;
    }
}
