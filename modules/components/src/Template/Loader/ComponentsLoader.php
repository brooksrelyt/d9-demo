<?php

namespace Drupal\components\Template\Loader;

use Drupal\components\Template\ComponentsRegistry;
use Twig\Loader\FilesystemLoader;

/**
 * Loads templates from the filesystem.
 *
 * This loader adds module and theme components paths as namespaces to the Twig
 * filesystem loader so that templates can be referenced by namespace, like
 * \@mycomponents/box.html.twig or \@mythemeComponents/page.html.twig.
 */
class ComponentsLoader extends FilesystemLoader {

  /**
   * The components registry service.
   *
   * @var \Drupal\components\Template\ComponentsRegistry
   */
  protected $componentsRegistry;

  /**
   * The active theme that the current namespaces are valid for.
   *
   * @var string
   */
  protected $activeTheme;

  /**
   * Constructs a new ComponentsLoader object.
   *
   * @param \Drupal\components\Template\ComponentsRegistry $componentsRegistry
   *   The components registry service.
   */
  public function __construct(ComponentsRegistry $componentsRegistry) {
    parent::__construct();

    $this->componentsRegistry = $componentsRegistry;
  }

  /**
   * Activates the proper namespaces if the active theme has changed.
   */
  public function init() {
    $activeTheme = $this->componentsRegistry->getActiveThemeName();

    // Update our namespaces if the active theme has changed.
    if ($this->activeTheme !== $activeTheme) {
      // Invalidate the cache.
      $this->cache = $this->errorCache = [];

      // Set the new paths.
      $this->paths = $this->componentsRegistry->getNamespaces();

      // Save the active theme for later use.
      $this->activeTheme = $activeTheme;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Twig\Error\LoaderError
   */
  protected function findTemplate($name, $throw = TRUE) {
    // The active theme might change during the request, so we wait until the
    // last possible moment to initialize before delivering a template.
    $this->init();

    return parent::findTemplate($name, $throw);
  }

}
