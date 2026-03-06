<?php

namespace WPML\Core\Component\PostHog\Domain\Event\OpenTranslationEditor;

use WPML\Core\Component\PostHog\Domain\Event\Event as AbstractEvent;

class Event extends AbstractEvent {


  public function getName(): string {
    return 'wpml_open_translation_editor';
  }


}
