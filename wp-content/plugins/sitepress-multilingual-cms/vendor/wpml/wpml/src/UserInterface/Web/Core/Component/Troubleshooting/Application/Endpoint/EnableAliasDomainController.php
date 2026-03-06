<?php

namespace WPML\UserInterface\Web\Core\Component\Troubleshooting\Application\Endpoint;

use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\TM\ATE\ClonedSites\Lock;
use WPML\TM\ATE\ClonedSites\SecondaryDomains;

class EnableAliasDomainController implements EndpointInterface {

  /** @var Lock */
  private $lock;

  /** @var SecondaryDomains */
  private $secondaryDomains;


  public function __construct( Lock $lock, SecondaryDomains $secondaryDomains ) {
    $this->lock             = $lock;
    $this->secondaryDomains = $secondaryDomains;
  }


  public function handle( $requestData = null ): array {
    $lockData = $this->lock->getLockData();

    $this->secondaryDomains->add(
      $lockData['urlUsedToMakeRequest'],
      $lockData['urlCurrentlyRegisteredInAMS']
    );
    $this->lock->unlock();

    return [ 'success' => true ];
  }


}
