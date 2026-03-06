<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\PostHog;

use WPML\Core\Component\PostHog\Application\Repository\PostHogDefaultRequestSentRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Service\RetryService;
use WPML\Core\Port\PluginInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ApiInterface;

class Controller implements ScriptPrerequisitesInterface, ScriptDataProviderInterface {

    /** @var Endpoint|null */
    private $endpoint;

    /** @var ApiInterface */
    private $api;

    /** @var PluginInterface */
    private $plugin;

    /** @var PostHogStateRepositoryInterface */
    private $postHogStateRepository;

    /** @var PostHogDefaultRequestSentRepositoryInterface */
    private $postHogDefaultRequestSentRepository;

    /** @var RetryService */
    private $retryService;


  public function __construct(
        ApiInterface $api,
        PluginInterface $plugin,
        PostHogStateRepositoryInterface $postHogStateRepository,
        PostHogDefaultRequestSentRepositoryInterface $postHogDefaultRequestSentRepository,
        RetryService $retryService
    ) {
      $this->api                                 = $api;
      $this->plugin                              = $plugin;
      $this->postHogStateRepository               = $postHogStateRepository;
      $this->postHogDefaultRequestSentRepository = $postHogDefaultRequestSentRepository;
      $this->retryService                        = $retryService;
  }


  public function scriptPrerequisitesMet(): bool {
      return $this->shouldMakeExternalRequest();
  }


  public function jsWindowKey(): string {
      return 'checkPostHogShouldRecord';
  }


  public function initialScriptData(): array {
      return [
          'route' => $this->api->getFullUrl( $this->getEndpoint() ),
          'nonce' => $this->api->nonce(),
      ];
  }


  private function shouldMakeExternalRequest(): bool {
      $canMakeRequest = $this->plugin->isSetupComplete() &&
                        ! $this->postHogStateRepository->isEnabled() &&
                        ! $this->postHogDefaultRequestSentRepository->isSent();

    if ( $canMakeRequest && $this->retryService->isInRetryMode() ) {
        return $this->retryService->shouldRetry();
    }

      return $canMakeRequest;
  }


  private function getEndpoint(): Endpoint {
    if ( $this->endpoint === null ) {
        $this->endpoint = new Endpoint( EndpointDataProvider::ID, EndpointDataProvider::PATH );
        $this->endpoint->setMethod( EndpointDataProvider::METHOD );
    }

      return $this->endpoint;
  }


}
