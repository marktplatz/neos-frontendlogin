<?php
namespace UpAssist\Neos\FrontendLogin\Domain\Model;

/*
 * This file is part of the UpAssist.Neos.FrontendLogin package.
 */

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;

/**
 * @Flow\Entity
 */
class AutoApprovedDomain
{

    /**
     * @var string
     * @ORM\Column(nullable=false, unique=true)
     */
    protected string $domain;

    public function __construct(?string $domain = null)
    {
        if ($domain) {
            $this->setDomain($domain);
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getDomain();
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @param string $domain
     * @return void
     */
    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }
}
