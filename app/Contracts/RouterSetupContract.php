<?php

namespace Application\Contracts;

/**
 * Interface RouterSetupContract
 */
interface RouterSetupContract
{
    /**
     * Defines all controller routes.
     */
    static public function routerSetup(): void;
}
