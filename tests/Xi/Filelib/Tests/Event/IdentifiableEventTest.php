<?php

namespace Xi\Filelib\Tests\Event;

use Xi\Filelib\Event\IdentifiableEvent;

class IdentifiableEventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function classExists()
    {
        $this->assertTrue(class_exists('Xi\Filelib\Event\IdentifiableEvent'));
        $this->assertTrue(
            is_subclass_of('Xi\Filelib\Event\IdentifiableEvent', 'Symfony\Contracts\EventDispatcher\Event')
        );
    }

    /**
     * @test
     */
    public function eventInitializesCorrectly()
    {
        $identifiable = $this->getMockBuilder('Xi\Filelib\Identifiable')->getMock();
        $event = new IdentifiableEvent($identifiable);

        $identifiable2 = $event->getIdentifiable();
        $this->assertSame($identifiable, $identifiable2);
    }
}
