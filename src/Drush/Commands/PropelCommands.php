<?php

namespace Drupal\propel\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\propel\PropelComponentsManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Defines Drush commands for Propel.
 */
class PropelCommands extends DrushCommands {

  /**
   * Components manager.
   *
   * @var \Drupal\propel\PropelComponentsManager
   */
  protected $propelComponentsManager;

  public function __construct(PropelComponentsManager $propelComponentsManager) {
    parent::__construct();
    $this->propelComponentsManager = $propelComponentsManager;
  }

  /**
   * Instantiates a new instance of this class.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The service container this instance should use.
   *
   * @return static
   *   A new class instance.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('propel.components_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger): void {
    parent::setLogger($logger);
    $this->propelComponentsManager->setLogger($logger);
  }

  /**
   * A custom Drush command that prints a message.
   *
   * @command propel:add
   * @usage propel:add my_component
   *   Adds a Propel component.
   */
  public function add($name = '') {
    if (empty($name)) {
      throw new \Exception("Error: No component name provided.");
    }
    $component_path = $this->propelComponentsManager->getSDCPath($name);
    $this->propelComponentsManager->downloadSDC($component_path);
  }

  /**
   * A command that downloads the base stylesheet and all the starter components.
   *
   * @command propel:init
   */
  public function init() {
    $this->propelComponentsManager->downloadStylesheets();
    $starter_components = [
      'accordion',
      'billboard',
      'card',
      'carousel',
      'header',
      'hero-banner',
      'layout--one-column',
      'layout--two-column',
      'layout--content-header',
      'footer',
      'form',
      'section-intro',
      'tabs',
      'text-and-media'
    ];
    foreach ($starter_components as $component) {
      $this->add($component);
    }
  }

}
