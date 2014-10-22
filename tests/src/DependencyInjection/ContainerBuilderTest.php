<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\DependencyInjection\ContainerBuilderTest
 */

namespace Drupal\render_cache\Tests\DependencyInjection;

use Drupal\render_cache\DependencyInjection\ContainerBuilder;
use Drupal\render_cache\DependencyInjection\ContainerInterface;
use Drupal\render_cache\DependencyInjection\ServiceProviderInterface;
use Drupal\render_cache\Plugin\PluginManagerInterface;

use Mockery;
use Mockery\MockInterface;

/**
 * @coversDefaultClass \Drupal\render_cache\DependencyInjection\ContainerBuilder
 * @group dic
 */
class ContainerBuilderTest extends \PHPUnit_Framework_TestCase {

  public function setUp() {
    // Setup the base container definition.
    $this->containerDefinition = $this->getFakeContainerDefinition();

    // Alter the definition in a specified way.
    $altered_definition = $this->containerDefinition;

    $altered_definition['services']['some_service']['tags'][] = array('bar' => array());
    $altered_definition['services']['some_service']['tags'][] = array('baz' => array());
    $altered_definition['parameters']['some_other_config'] = 'lama';

    $this->alteredDefinition = $altered_definition;

    // Create a service provider providing these definitions.

    $service_provider = Mockery::mock('\Drupal\render_cache\DependencyInjection\ServiceProviderInterface');
    $service_provider->shouldReceive('getContainerDefinition')
      ->once()
      ->andReturn($this->containerDefinition);

    $service_provider->shouldReceive('alterContainerDefinition')
      ->with(
        Mockery::on(function(&$container_definition) {
          $container_definition['services']['some_service']['tags'][] = array('bar' => array());
          $container_definition['services']['some_service']['tags'][] = array('baz' => array());
          $container_definition['parameters']['some_other_config'] = 'lama';
          return TRUE;
        })
      )
      ->once();

    $this->serviceProvider = $service_provider;

    // Set up definitions for the Fake plugin manager.
    $definitions = array(
      'fake_provider' => array(
        'class' => '\Drupal\render_cache\Tests\DependencyInjection\FakeProvider',
      ),
    );

    // And create a static plugin manager mock.
    $service_provider_manager = Mockery::mock('\Drupal\render_cache\Plugin\PluginManagerInterface', array(
      'getDefinitions' => $definitions,
      'getDefinition' => $definitions['fake_provider'],
      'hasDefinition' => TRUE,
      'createInstance' => $this->serviceProvider,
      'getInstance' => $this->serviceProvider,
    ));
    $this->serviceProviderManager = $service_provider_manager;
  }

  /**
   * Tests that the controller interface has a view method.
   */
  public function testGetContainerDefinition() {
    // We need to use a partial mock as the alter method calls procedural code.
    $container_builder = Mockery::mock('\Drupal\render_cache\DependencyInjection\ContainerBuilder[moduleAlter]', array($this->serviceProviderManager));
    $container_builder->shouldAllowMockingProtectedMethods();
    $container_builder->shouldReceive('moduleAlter')
      ->once();

    $definition = $container_builder->getContainerDefinition();
    $this->assertEquals($this->alteredDefinition, $definition);
  }

  public function testAlter() {
    $container_builder = Mockery::mock('\Drupal\render_cache\DependencyInjection\ContainerBuilder[moduleAlter]', array($this->serviceProviderManager));
    $container_builder->shouldAllowMockingProtectedMethods();

    $container_builder->shouldReceive('moduleAlter')
      ->with(
        Mockery::on(function(&$container_definition) {
          $container_definition['services']['foo'] = array('class' => 'FooService');
          return TRUE;
        })
      );
    $altered_definition = $this->alteredDefinition;
    $altered_definition['services']['foo'] = array('class' => 'FooService');

    $definition = $container_builder->getContainerDefinition();
    $this->assertEquals($altered_definition, $definition);
  }
 
  public function testCompile() {
    // Create a fake container class implementing the interface.
    $fake_container = Mockery::mock('\Drupal\render_cache\DependencyInjection\ContainerInterface');
    $fake_container_class = get_class($fake_container);

    $container_builder = Mockery::mock('\Drupal\render_cache\DependencyInjection\ContainerBuilder[moduleAlter]', array($this->serviceProviderManager));
    $container_builder->shouldAllowMockingProtectedMethods();
    $container_builder->shouldReceive('moduleAlter')
      ->with(
        Mockery::on(function(&$container_definition) use ($fake_container_class) {
          $container_definition['services']['container']['class'] = $fake_container_class;
          return TRUE;
        })
      );

    $container = $container_builder->compile();

    // Check this returns the right expected class and interfaces.
    $this->assertEquals($container instanceof ContainerInterface, TRUE);
    $this->assertEquals($container instanceof MockInterface, TRUE);
    $this->assertEquals($container instanceof $fake_container_class, TRUE);
  }

  protected function getFakeContainerDefinition() {
    $parameters = array();
    $parameters['some_config'] = 'foo';
    $parameters['some_other_config'] = 'kitten';

    $services = array();
    $services['container'] = array(
      'class' => '\Drupal\render_cache\DependencyInjection\Container',
    );
    $services['some_service'] = array(
      'class' => '\Drupal\render_cache\Service\SomeService',
      'arguments' => array('@container', '%some_config'),
      'calls' => array('setContainer', array('@container')),
      'tags' => array(
        array('service' => array()),
      ),
      'priority' => 0,
    );

    return array(
      'parameters' => $parameters,
      'services' => $services,
    );
  }
}