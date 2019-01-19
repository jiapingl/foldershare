<?php

namespace Drupal\Tests\fs\Unit\FolderShareCommand;

use Drupal\KernelTests\KernelTestBase;

use Drupal\fs\Plugin\FolderShareCommand\FolderShareCommandInterface;

/**
 * Unit tests the FolderShareCommandManager class.
 *
 * @group fs
 *
 * @coversDefaultClass \Drupal\fs\FolderShareCommand\FolderShareCommandManager
 */
class FolderShareCommandManagerTest extends KernelTestBase {

  /*---------------------------------------------------------------------
   * Setup
   *---------------------------------------------------------------------*/

  /**
   * Set up a test.
   */
  public function setUp() {
    parent::setUp();
  }

  /*---------------------------------------------------------------------
   * Tests
   *---------------------------------------------------------------------*/

  /**
   * Tests get of the manager.
   */
  public function testGetManager() {
    // Get the manager.
    $manager = \Drupal::service('fs.plugin.manager.foldersharecommand');
    $this->assertNotEquals(
      NULL,
      $manager,
      'Correctly found FolderShareCommandManager');
  }

  /**
   * Tests getting definitions of the manager.
   */
  public function testGetDefinitions() {

    // Get the manager.
    $manager = \Drupal::service('fs.plugin.manager.foldersharecommand');

    // Get all definitions.
    $defs = $manager->getDefinitions();
    $this->assertNotEquals(
      NULL,
      $defs,
      'Correctly found FolderShareCommand general definition array');
    $this->assertTrue(
      count($defs) > 1,
      'Correctly found non-empty array of FolderShareCommand general definitions');

    // Get all definitions by type.
    $defs = $manager->getDefinitions('foldersharecommand');
    $this->assertNotEquals(
      NULL,
      $defs,
      'Correctly found FolderShareCommand command definition array');
    $this->assertTrue(
      count($defs) > 1,
      'Correctly found non-empty array of FolderShareCommand command definitions');

    // Get a specific definition.
    $def = $defs->getDefinition('foldersharecommand_bogus');
    $this->assertTrue(
      empty($def),
      'Correctly found no definition of a bogus command');
  }

  /**
   * Tests creating an instance of an action.
   */
  public function testCreateInstance() {
    // Get the manager.
    $manager = \Drupal::service('fs.plugin.manager.foldersharecommand');

    // Create a command instance.
    $command = $manager->createInstance('foldersharecommand_new_root_folder');
    $this->assertNotEquals(
      NULL,
      $command,
      'Correctly created a new command instance');
    $this->assertTrue(
      $command instanceof FolderShareCommandInterface,
      'Correctly found command is instance of FolderShareCommandInterface');
  }

}
