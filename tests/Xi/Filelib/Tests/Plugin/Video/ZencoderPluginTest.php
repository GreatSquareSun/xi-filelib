<?php

/**
 * This file is part of the Xi Filelib package.
 *
 * For copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Services_Zencoder as ZencoderService;
use Services_Zencoder_Exception as ZencoderException;
use Xi\Filelib\File\File;
use Xi\Filelib\Resource\Resource;
use Xi\Filelib\Plugin\Video\ZencoderPlugin;
use Xi\Filelib\Events;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Version;
use Aws\S3\S3Client;
use Ramsey\Uuid\Uuid;

/**
 * @group plugin
 */
class ZencoderPluginTest extends \Xi\Filelib\Tests\TestCase
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var ZencoderPlugin
     */
    private $plugin;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $storage;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $zencoderService;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $amazonService;

    public function setUp()
    {
        if (!class_exists(S3Client::class)) {
            return;
        }

        if (!getenv('S3_KEY')) {
            $this->markTestSkipped('S3 not configured');
        }

        if (!getenv('ZENCODER_KEY')) {
            $this->markTestSkipped('Zencoder service not configured');
        }

        if (!class_exists('Services_Zencoder')) {
            $this->markTestSkipped('ZencoderService class could not be loaded');
        }

        if (!class_exists('Aws\S3\S3Client')) {
            $this->markTestSkipped('AWS client class could not be loaded');
        }

        $this->config = array(
            'apiKey' => 'api key',
            'awsKey' => 'aws key',
            'awsSecretKey' => 'aws secret key',
            'awsBucket' => 'aws bucket',
            'sleepyTime' => 5,
            'outputs' => array(
                'pygmi' => array(
                    'extension' => 'mp4',
                        'output' => array(
                        'label' => 'pygmi',
                        'device_profile' => 'mobile/baseline'
                    ),
                ),
                'watussi' => array(
                    'extension' => 'mp4',
                    'output' => array(
                        'label' => 'watussi',
                        'device_profile' => 'mobile/advanced'
                    ),
                )
            )
        );

        $this->storage = $this->getMockedStorage();

        $this->amazonService = $this->getMockedAwsService();

        $this->zencoderService = $this->getMockBuilder('Services_Zencoder')
            ->disableOriginalConstructor()
            ->getMock();
        $this->zencoderService->jobs = $this->getMockBuilder('Services_Zencoder_Jobs')
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new ZencoderPlugin(
            getenv('ZENCODER_KEY'),
            getenv('S3_BUCKET'),
            $this->amazonService,
            $this->config['outputs']
        );


        $pm = $this->getMockedProfileManager(array('lusso'));

        $filelib = $this->getMockedFilelib(null, null, null, $this->storage, null, null, null, null, $pm);
        $this->plugin->attachTo($filelib);
    }

    /**
     * @test
     */
    public function settersAndGettersShouldWorkAsExpected()
    {
        $this->assertEquals(getenv('S3_BUCKET'), $this->plugin->getAwsBucket());

        $val = 1;
        $this->assertEquals(5, $this->plugin->getSleepyTime());
        $this->assertSame($this->plugin, $this->plugin->setSleepyTime($val));
        $this->assertEquals($val, $this->plugin->getSleepyTime());
    }

    /**
     * @test
     */
    public function getServiceShouldReturnAndCacheZencoderService()
    {
        $service = $this->plugin->getService();
        $this->assertInstanceOf('Services_Zencoder', $service);
        $this->assertSame($service, $this->plugin->getService());
    }

    /**
     * @test
     */
    public function getClientShouldReturnAndCacheAwsService()
    {
        $service = $this->plugin->getClient();
        $this->assertInstanceOf('Aws\S3\S3Client', $service);
        $this->assertSame($service, $this->plugin->getClient());
    }

    /**
     * @test
     */
    public function getExtensionShouldDigOutputsForTheCorrectExtension()
    {
        $outputs = array(
            'pygmi' => array(
                'extension' => 'lussen',
                    'output' => array(
                    'label' => 'pygmi',
                    'device_profile' => 'mobile/baseline'
                ),
            ),
            'watussi' => array(
                'extension' => 'dorfer',
                'output' => array(
                    'label' => 'watussi',
                    'device_profile' => 'mobile/advanced'
                ),
            )
        );

        $this->plugin->setOutputs($outputs);

        $file = $this->getMockedFile();

        $this->assertEquals('lussen', $this->plugin->getExtension($file, Version::get('pygmi')));
        $this->assertEquals('dorfer', $this->plugin->getExtension($file, Version::get('watussi')));

        $this->assertEquals('png', $this->plugin->getExtension($file, Version::get('watussi_thumbnail')));

    }

    /**
     * @test
     */
    public function getProvidedVersionsShouldReturnCorrectVersions()
    {
        $this->assertEquals(
            array('pygmi', 'watussi', 'pygmi_thumbnail', 'watussi_thumbnail'),
            $this->plugin->getProvidedVersions()
        );
    }


    public function provideMimetypes()
    {
        return array(
            array(false, 'image/png'),
            array(true, 'video/ogg'),
        );
    }

    /**
     * @test
     * @dataProvider provideMimetypes
     */
    public function shouldProvideForVideo($expected, $mimetype)
    {
        $file = File::create(
            array(
                'uuid' => Uuid::uuid4()->toString(),
                'profile' => 'default',
                'resource' => Resource::create(
                    array(
                        'mimetype' => $mimetype
                    )
                )
            )
        );

        $filelib = new FileLibrary(
            $this->getMockedStorageAdapter(),
            $this->getMockedBackendAdapter()
        );
        $filelib->addPlugin($this->plugin);

        $this->assertSame($expected, $this->plugin->isApplicableTo($file));
    }


    /**
     * @test
     */
    public function getOutputsToZencoderShouldReturnCorrectData()
    {
        $ret = $this->plugin->getOutputsToZencoder();
        $this->assertInternalType('array', $ret);
        $this->assertCount(sizeof($this->config['outputs']), $ret);

        foreach ($ret as $rut) {
            $this->assertArrayHasKey('label', $rut);
        }
    }

    /**
     * @test
     */
    public function classShouldExist()
    {
        $this->assertTrue(class_exists('Xi\Filelib\Plugin\Video\ZencoderPlugin'));
        $this->assertArrayHasKey('Xi\Filelib\Plugin\BasePlugin', class_parents('Xi\Filelib\Plugin\Video\ZencoderPlugin'));
    }

    /**
     * @test
     */
    public function createProvidedVersionsShouldCreateVersions()
    {
        $this->setupStubsForZencoderService();
        $this->plugin->setService($this->zencoderService);

        $this->amazonService
            ->expects($this->at(0))
            ->method('putObject')
            ->with($this->isType('array'));

        $this->amazonService
            ->expects($this->at(1))
            ->method('deleteObject')
            ->with($this->isType('array'));

        $file = File::create(
            array(
                'id' => 1,
                'uuid' => Uuid::uuid4()->toString(),
                'name' => 'hauska-joonas.mp4',
                'resource' => Resource::create(array('id' => 1)))
        );

        $this->storage
            ->expects($this->once())
            ->method('retrieve')
            ->with($this->isInstanceOf('Xi\Filelib\Resource\Resource'))
            ->will($this->returnValue(ROOT_TESTS . '/data/hauska-joonas.mp4'));

        $this->plugin->setSleepyTime(0);

        $ret = $this->plugin->createAllTemporaryVersions($file);

        $this->assertInternalType('array', $ret);
        $this->assertCount(4, $ret);
        $this->assertArrayHasKey('pygmi', $ret);
        $this->assertArrayHasKey('watussi', $ret);
        $this->assertArrayHasKey('pygmi_thumbnail', $ret);
        $this->assertArrayHasKey('watussi_thumbnail', $ret);
    }

    private function setupStubsForZencoderService()
    {
        $this->zencoderService->outputs = $this->getMockBuilder('Services_Zencoder_Outputs')
            ->disableOriginalConstructor()
            ->getMock();

        $job = $this->getMockBuilder('Services_Zencoder_Job')
            ->disableOriginalConstructor()
            ->getMock();

        $this->zencoderService->jobs->expects($this->once())->method('create')
            ->will($this->returnValue($job));

        $job->outputs['watussi'] = $this->getMockBuilder('Services_Zencoder_Output')
            ->disableOriginalConstructor()
            ->getMock();
        $job->outputs['watussi']->id = 1;

        $job->outputs['pygmi'] = $this->getMockBuilder('Services_Zencoder_Output')
            ->disableOriginalConstructor()
            ->getMock();
        $job->outputs['pygmi']->id = 2;

        $progressFinished = $this->getMockBuilder('Services_Zencoder_Progress')
            ->disableOriginalConstructor()
            ->getMock();
        $progressFinished->state = 'finished';

        $progressUnfinished = $this->getMockBuilder('Services_Zencoder_Progress')
            ->disableOriginalConstructor()
            ->getMock();
        $progressUnfinished->state = 'waiting';

        $this->zencoderService->outputs->expects($this->at(0))->method('progress')
            ->with($this->equalTo(2))
            ->will($this->returnValue($progressUnfinished));

        $this->zencoderService->outputs->expects($this->at(1))->method('progress')
            ->with($this->equalTo(1))
            ->will($this->returnValue($progressUnfinished));

        $this->zencoderService->outputs->expects($this->at(2))->method('progress')
            ->with($this->equalTo(2))
            ->will($this->returnValue($progressFinished));

        $this->zencoderService->outputs->expects($this->at(3))->method('progress')
            ->with($this->equalTo(1))
            ->will($this->returnValue($progressFinished));

        $this->zencoderService->outputs->expects($this->at(4))
            ->method('details')
            ->with($this->equalTo(2))
            ->will($this->returnValue($this->getDetails()));

        $this->zencoderService->outputs->expects($this->at(5))
            ->method('details')
            ->with($this->equalTo(1))
            ->will($this->returnValue($this->getDetails()));
    }

    /**
     * @test
     */
    public function createProvidedVersionsShouldThrowExceptionOnZencoderError()
    {
        $this->plugin->setService($this->zencoderService);

        $this->zencoderService->jobs->expects($this->once())->method('create')
            ->will($this->throwException(
                new ZencoderException(
                    'I threw up',
                    json_encode(array('errors' => array('Url of input file is invalid', 'lus')))
                )
            ));

        $this->amazonService
            ->expects($this->at(0))
            ->method('putObject')
            ->with($this->isType('array'));

        $file = File::create(
            array('id' => 1,
                'uuid' => Uuid::uuid4()->toString(),
                'name' => 'hauska-joonas.mp4',
                'resource' => Resource::create(array('id' => 1)))
        );

        $this->storage
            ->expects($this->once())
            ->method('retrieve')
            ->with($this->isInstanceOf('Xi\Filelib\Resource\Resource'))
            ->will($this->returnValue(ROOT_TESTS . '/data/hauska-joonas.mp4'));

        $this->setExpectedException(
            'Xi\Filelib\FilelibException',
            'Zencoder service responded with errors: Url of input file is invalid. lus',
            500
        );

        $this->plugin->createAllTemporaryVersions($file);
    }

    private function getMockedAwsService()
    {
        return $this->getMockBuilder('Aws\S3\S3Client')
                    ->disableOriginalConstructor()
                    ->setMethods(array('putObject', 'deleteObject'))
                    ->getMock();
    }

    /**
     * @test
     */
    public function pluginShouldAllowSharedResource()
    {
        $this->assertTrue($this->plugin->isSharedResourceAllowed());
    }

    /**
     * @test
     */
    public function pluginShouldAllowSharedVersions()
    {
        $this->assertTrue($this->plugin->areSharedVersionsAllowed());
    }

    /**
     * @test
     */
    public function getSubscribedEventsShouldReturnCorrectEvents()
    {
        $events = ZencoderPlugin::getSubscribedEvents();
        $this->assertArrayHasKey(Events::PROFILE_AFTER_ADD, $events);
        $this->assertArrayHasKey(Events::FILE_AFTER_AFTERUPLOAD, $events);
        $this->assertArrayHasKey(Events::FILE_AFTER_DELETE, $events);
        $this->assertArrayHasKey(Events::RESOURCE_AFTER_DELETE, $events);
    }

    private function getDetails()
    {
        $details = $this->getMockBuilder('Services_Zencoder_Output')
            ->disableOriginalConstructor()
            ->getMock();
        $details->url = ROOT_TESTS . '/data/hauska-joonas.mp4';

        $thumbs = new stdClass;
        $thumbs->url = ROOT_TESTS . '/data/self-lussing-manatee.jpg';

        $images = new stdClass;
        $images->images = array($thumbs);

        $details->thumbnails = array(
            $images
        );

        return $details;
    }

    /**
     * @return array
     */
    public function provideVersionsAndValidities()
    {
        return array(
            array('pygmi', true),
            array('pygmi@mod', false),
            array('watussi', true),
            array('watussi::xoo:xoo', false),
            array('watussix', false),
            array('watussi_thumbnail', true),
            array('watussi__thumbnail', false),
            array('pygmi_thumbnailx', false),
        );
    }

    /**
     * @test
     * @dataProvider provideVersionsAndValidities
     */
    public function checksVersionValidity($version, $expected)
    {
        if (!$expected) {
            $this->setExpectedException('Xi\Filelib\InvalidVersionException');
        }

        $plugin = $this->plugin;

        $version = Version::get($version);
        $version2 = $plugin->ensureValidVersion($version);
        $this->assertSame($version, $version2);
        $this->assertInstanceOf('Xi\Filelib\Version', $version2);
    }

}
