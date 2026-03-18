<?php
namespace UpAssist\Neos\FrontendLogin\Domain\Repository;

/*
 * This file is part of the UpAssist.Neos.FrontendLogin package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\Repository;

/**
 * @Flow\Scope("singleton")
 */
class AutoApprovedDomainRepository extends Repository
{

    /**
     * @var array
     */
    protected $defaultOrderings = [
        'domain' => QueryInterface::ORDER_ASCENDING
    ];

}
