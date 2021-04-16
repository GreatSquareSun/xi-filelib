<?php

namespace Xi\Filelib\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Xi\Filelib\Backend\Adapter\DoctrineDbalBackendAdapter;
use Xi\Filelib\Backend\Adapter\MongoBackendAdapter;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Storage\Adapter\FilesystemStorageAdapter;
use Xi\Filelib\Plugin\RandomizeNamePlugin;
use Xi\Filelib\Backend\Cache\Adapter\MemcachedCacheAdapter;
use Xi\Filelib\Backend\Cache\Adapter\RedisCacheAdapter;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;
use Xi\Filelib\Publisher\Publisher;
use Xi\Filelib\Publisher\Adapter\Filesystem\SymlinkFilesystemPublisherAdapter;
use Xi\Filelib\Publisher\Linker\BeautifurlLinker;
use Xi\Filelib\Plugin\VersionProvider\OriginalVersionPlugin;
use Xi\Filelib\Plugin\Image\VersionPlugin;
use Xi\Filelib\Authorization\Adapter\SimpleAuthorizationAdapter;
use Xi\Filelib\Authorization\AuthorizationPlugin;
use MongoClient;
use MongoConnectionException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Imagick;
use MongoDB;
use Memcached;
use Redis;

class TestCase extends \Xi\Filelib\Tests\TestCase
{

    /**
     * @var FileLibrary
     */
    protected $filelib;

    /**
     * @var MongoDB
     */
    protected $mongo;

    /**
     * @var Publisher
     */
    protected $publisher;

    /**
     * @var SimpleAuthorizationAdapter
     */
    protected $authorizationAdapter;

    /**
     * @var Memcached
     */
    protected $memcached;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var Connection
     */
    protected $conn;


    public function setUp()
    {
        /*
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped('MongoDB extension is not loaded.');
        }

        try {
            $mongoClient = new MongoClient(MONGO_DNS);
        } catch (MongoConnectionException $e) {
            return $this->markTestSkipped('Can not connect to MongoDB.');
        }


        $this->mongo = $mongoClient->selectDb('filelib_tests');
        */
        $stopwatch = new Stopwatch();

        $ed = new TraceableEventDispatcher(new EventDispatcher(), $stopwatch);

        $this->conn = DriverManager::getConnection(
            array(
                'driver' => 'pdo_' . PDO_DRIVER,
                'dbname' => PDO_DBNAME,
                'user' => PDO_USERNAME,
                'password' => PDO_PASSWORD,
                'host' => PDO_HOST,
            )
        );

        $filelib = new FileLibrary(
            new FilesystemStorageAdapter(ROOT_TESTS . '/data/files'),
            new DoctrineDbalBackendAdapter(
                $this->conn
            ),
            $ed
        );

        if (class_exists('Memcached')) {
            $memcached = new Memcached();
            $memcached->addServer('localhost', 11211);

            $this->memcached = $memcached;
            $this->memcached->flush();
        }

        if (class_exists('Redis')) {
            $this->redis = new Redis();
            $this->redis->connect('redis-node', 6379);
        }

        $filelib->addPlugin(new RandomizeNamePlugin());

        $authorizationAdapter = new SimpleAuthorizationAdapter();
        $authorizationPlugin = new AuthorizationPlugin($authorizationAdapter);
        $filelib->addPlugin($authorizationPlugin, array('default'));

        $authorizationAdapter
            ->setFolderWritable(true)
            ->setFileReadableByAnonymous(true)
            ->setFileReadable(true);
        $this->authorizationAdapter = $authorizationAdapter;

        $publisher = new Publisher(
            new SymlinkFilesystemPublisherAdapter(ROOT_TESTS . '/data/publisher/public', '600', '700', 'files'),
            new BeautifurlLinker()
        );
        $publisher->attachTo($filelib);
        $this->publisher = $publisher;

        $originalPlugin = new OriginalVersionPlugin('original');
        $filelib->addPlugin($originalPlugin, array('default'));

        $versionPlugin = new VersionPlugin(
            array(
                'cinemascope' => array(
                    array(
                        array('setImageCompression',Imagick::COMPRESSION_JPEG),
                        array('setImageFormat', 'jpg'),
                        array('setImageCompressionQuality', 50),
                        array('cropThumbnailImage', array(800, 200)),
                        array('sepiaToneImage', 90),
                        'Xi\Filelib\Plugin\Image\Command\WatermarkCommand' => array(ROOT_TESTS . '/data/watermark.png', 'se', 10),
                    )
                ),
                'croppo' => array(
                    array(
                        array('setImageCompression',Imagick::COMPRESSION_JPEG),
                        array('setImageFormat', 'jpg'),
                        array('setImageCompressionQuality', 80),
                        array('cropThumbnailImage', array(400, 400)),
                        'Xi\Filelib\Plugin\Image\Command\WatermarkCommand' => array(ROOT_TESTS . '/data/watermark.png', 'se', 10),
                    )
                )
            )
        );
        $filelib->addPlugin($versionPlugin, array('default'));

        $this->filelib = $filelib;
    }

    protected function tearDown()
    {
        $paths = array(
            ROOT_TESTS . '/data/files',
            ROOT_TESTS . '/data/publisher/public'
        );

        foreach ($paths as $path) {

            $diter = new RecursiveDirectoryIterator($path);
            $riter = new RecursiveIteratorIterator($diter, RecursiveIteratorIterator::CHILD_FIRST);

            foreach ($riter as $item) {
                if (($item->isFile() || $item->isLink()) && $item->getFilename() !== '.gitignore') {
                    @unlink($item->getPathName());
                }
            }

            foreach ($riter as $item) {
                if ($item->isDir() && !in_array($item->getPathName(), array('.', '..'))) {
                    @rmdir($item->getPathName());
                }
            }
        }

        $this->conn->exec("DELETE FROM xi_filelib_file");
        $this->conn->exec("DELETE FROM xi_filelib_resource");
        $this->conn->exec("UPDATE xi_filelib_folder SET parent_id = NULL");
        $this->conn->exec("DELETE FROM xi_filelib_folder");

        /*
        $this->mongo->selectCollection('resources')->drop();
        $this->mongo->selectCollection('folders')->drop();
        $this->mongo->selectCollection('files')->drop();
        */
    }


    public function assertStorageFileCount($expectedCount)
    {
        $count = 0;

        $diter = new RecursiveDirectoryIterator(ROOT_TESTS . '/data/files');
        $riter = new RecursiveIteratorIterator($diter, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($riter as $item) {
            if ($item->isFile() && $item->getFilename() !== '.gitignore') {
                $count = $count + 1;
            }
        }

        $this->assertEquals($expectedCount, $count);
    }

    public function assertPublisherFileCount($expectedCount)
    {
        $count = 0;

        $diter = new RecursiveDirectoryIterator(ROOT_TESTS . '/data/publisher/public');
        $riter = new RecursiveIteratorIterator($diter, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($riter as $item) {
            if (($item->isFile() || $item->isLink()) && $item->getFilename() !== '.gitignore') {
                $count = $count + 1;
            }
        }

        $this->assertEquals($expectedCount, $count);
    }

    protected function setupCache($enabled)
    {
        if ($enabled) {
            if ($this->memcached) {
                $this->filelib->createCacheFromAdapter(
                    new MemcachedCacheAdapter($this->memcached)
                );

                return;
            }
            if ($this->redis) {
                $this->filelib->createCacheFromAdapter(
                    new RedisCacheAdapter($this->redis)
                );

                return;
            }
            throw new \LogicException("No CacheAdapter found");
        }
    }

}
