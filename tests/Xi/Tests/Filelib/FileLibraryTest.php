<?php

namespace Xi\Tests\Filelib;

use Xi\Filelib\FileLibrary;
use Xi\Filelib\File\FileProfile;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FileLibraryTest extends TestCase
{
    private $dirname;
    
    public function setUp()
    {
        parent::setUp();
        $this->dirname = ROOT_TESTS . '/data/publisher/unwritable_dir';
        
        chmod($this->dirname, 0444);
        
    }
    
    
    public function tearDown()
    {
        parent::tearDown();
        chmod($this->dirname, 0755);
        
    }
    
    
    /**
     * @test
     */
    public function classShouldExist()
    {
        $this->assertTrue(class_exists('Xi\Filelib\FileLibrary'));
    }

    /**
     * @test
     */
    public function storageSetterAndGetterShouldWorkAsExpected()
    {
        $filelib = new FileLibrary();
        $obj = $this->getMockForAbstractClass('Xi\Filelib\Storage\Storage');
        $obj->expects($this->once())->method('setFilelib')->with($this->isInstanceOf('Xi\Filelib\FileLibrary'));
        $this->assertEquals(null, $filelib->getStorage());
        $this->assertSame($filelib, $filelib->setStorage($obj));
        $this->assertSame($obj, $filelib->getStorage());
    }

    
    /**
     * @test
     */
    public function publisherSetterAndGetterShouldWorkAsExpected()
    {
        $filelib = new FileLibrary();
        $obj = $this->getMockForAbstractClass('Xi\Filelib\Publisher\Publisher');
        $obj->expects($this->once())->method('setFilelib')->with($this->isInstanceOf('Xi\Filelib\FileLibrary'));
        $this->assertEquals(null, $filelib->getPublisher());
        $this->assertSame($filelib, $filelib->setPublisher($obj));
        $this->assertSame($obj, $filelib->getPublisher());
    }

    
    /**
     * @test
     */
    public function aclSetterAndGetterShouldWorkAsExpected()
    {
        $filelib = new FileLibrary();
        $obj = $this->getMockForAbstractClass('Xi\Filelib\Acl\Acl');
        // @todo: maybe $obj->expects($this->once())->method('setFilelib')->with($this->isInstanceOf('Xi\Filelib\FileLibrary'));
        $this->assertEquals(null, $filelib->getAcl());
        $this->assertSame($filelib, $filelib->setAcl($obj));
        $this->assertSame($obj, $filelib->getAcl());
    }
    
    
    /**
     * @test
     */
    public function backendSetterAndGetterShouldWorkAsExpected()
    {
        $filelib = new FileLibrary();
        $obj = $this->getMockForAbstractClass('Xi\Filelib\Backend\Backend');
        $obj->expects($this->once())->method('setFilelib')->with($this->isInstanceOf('Xi\Filelib\FileLibrary'));
        $this->assertEquals(null, $filelib->getBackend());
        $this->assertSame($filelib, $filelib->setBackend($obj));
        $this->assertSame($obj, $filelib->getBackend());
        
    }
    
    /**
     * @test
     */
    public function tempDirShouldDefaultToSystemTempDir()
    {
        $filelib = new FileLibrary();
        $this->assertEquals(sys_get_temp_dir(), $filelib->getTempDir());
    }
    
    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function setTempDirShouldFailWhenDirectoryDoesNotExists()
    {
        $filelib = new FileLibrary();
        $filelib->setTempDir(ROOT_TESTS . '/nonexisting_directory');
    }
    
    
     /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function setTempDirShouldFailWhenDirectoryIsNotWritable()
    {
        $dirname = ROOT_TESTS . '/data/publisher/unwritable_dir';
        $this->assertTrue(is_dir($this->dirname));
        $this->assertFalse(is_writable($this->dirname));
                
        $filelib = new FileLibrary();
        $filelib->setTempDir($dirname);
    }
    
    /**
     * @test
     */
    public function fileShouldDelegateToGetFileOperator()
    {
        $filelib = $this->getMockBuilder('Xi\Filelib\FileLibrary')->setMethods(array('getFileOperator'))->getMock();
        $filelib->expects($this->once())->method('getFileOperator');
        $filelib->file();
    }
    
    /**
     * @test
     */
    public function folderShouldDelegateToGetFolderOperator()
    {
        $filelib = $this->getMockBuilder('Xi\Filelib\FileLibrary')->setMethods(array('getFolderOperator'))->getMock();
        $filelib->expects($this->once())->method('getFolderOperator');
        $filelib->folder();
    }
    
    /**
     * @test
     */
    public function getFileOperatorShouldDefaultToDefaultFileOperator()
    {
        $filelib = new FileLibrary();
        $fop = $filelib->getFileOperator();
        
        $this->assertEquals('Xi\Filelib\File\DefaultFileOperator', get_class($fop));
    }
    
    /**
     * @test
     */
    public function getFolderOperatorShouldDefaultToDefaultFolderOperator()
    {
        $filelib = new FileLibrary();
        $fop = $filelib->getFolderOperator();
        $this->assertEquals('Xi\Filelib\Folder\DefaultFolderOperator', get_class($fop));
    }
    
    /**
     * @test
     */
    public function setFileOperatorShouldOverrideDefaultFileOperator()
    {
        $mock = $this->getMockForAbstractClass('Xi\Filelib\File\FileOperator');
        $filelib = new FileLibrary();
        $this->assertSame($filelib, $filelib->setFileOperator($mock));
        $this->assertSame($mock, $filelib->getFileOperator());
    }
    
    /**
     * @test
     */
    public function setFolderOperatorShouldOverrideDefaultFolderOperator()
    {
        $mock = $this->getMockForAbstractClass('Xi\Filelib\Folder\FolderOperator');
        $filelib = new FileLibrary();
        $this->assertSame($filelib, $filelib->setFolderOperator($mock));
        $this->assertSame($mock, $filelib->getFolderOperator());
    }
    
    /**
     * @test
     */
    public function setFileItemClassShouldDelegateToFileOperator()
    {
        $fop = $this->getMockForAbstractClass('Xi\Filelib\File\FileOperator');
        $fop->expects($this->once())->method('setClass')->with($this->equalTo('lussenhofer'));
                
        $filelib = new FileLibrary();
        $filelib->setFileOperator($fop);
        
        $filelib->setFileItemClass('lussenhofer');

    }
    

    /**
     * @test
     */
    public function getFileItemClassShouldDelegateToFileOperator()
    {
        $fop = $this->getMockForAbstractClass('Xi\Filelib\File\FileOperator');
        $fop->expects($this->once())->method('getClass');
                
        $filelib = new FileLibrary();
        $filelib->setFileOperator($fop);
        
        $filelib->getFileItemClass('lussenhofer');

    }

    
    /**
     * @test
     */
    public function setFolderItemClassShouldDelegateToFolderOperator()
    {
        $fop = $this->getMockForAbstractClass('Xi\Filelib\Folder\FolderOperator');
        $fop->expects($this->once())->method('setClass')->with($this->equalTo('lussenhofer'));
                
        $filelib = new FileLibrary();
        $filelib->setFolderOperator($fop);
        
        $filelib->setFolderItemClass('lussenhofer');

    }
    

    /**
     * @test
     */
    public function getFolderItemClassShouldDelegateToFolderOperator()
    {
        $fop = $this->getMockForAbstractClass('Xi\Filelib\Folder\FolderOperator');
        $fop->expects($this->once())->method('getClass');
                
        $filelib = new FileLibrary();
        $filelib->setFolderOperator($fop);
        
        $filelib->getFolderItemClass('lussenhofer');

    }
    
    /**
     * @test
     */
    public function getProfilesShouldDelegateToFileOperator()
    {
        $fop = $this->getMockForAbstractClass('Xi\Filelib\File\FileOperator');
        $fop->expects($this->once())->method('getProfiles');
                
        $filelib = new FileLibrary();
        $filelib->setFileOperator($fop);
        $filelib->getProfiles();
        
        
        
    }

    /**
     * @test
     */
    public function addProfileShouldDelegateToFileOperator()
    {

        $profile = $this->getMock('Xi\Filelib\File\FileProfile');
        
        $fop = $this->getMockForAbstractClass('Xi\Filelib\File\FileOperator');
        $fop->expects($this->once())->method('addProfile')->with($this->equalTo($profile));
                
        $filelib = new FileLibrary();
        $filelib->setFileOperator($fop);

        $filelib->addProfile($profile);
        
    }

    
    
    /**
     * @test
     */
    public function addPluginShouldDelegateToFileOperator()
    {
        $fop = $this->getMockForAbstractClass('Xi\Filelib\File\FileOperator');
        $fop->expects($this->once())->method('addPlugin')->with($this->isInstanceOf('Xi\Filelib\Plugin\Plugin'));
        
        $plugin = $this->getMockForAbstractClass('Xi\Filelib\Plugin\Plugin');
        $plugin->expects($this->once())->method('init');
                
        $filelib = new FileLibrary();
        $filelib->setFileOperator($fop);
                
        $plugin->expects($this->once())->method('setFilelib')->with($this->equalTo($filelib));
        
        $filelib->addPlugin($plugin);
        
        
    }
    
    
    /**
     * @test
     */
    public function getEventDispatcherShouldDefaultToSymfonyDefaultImplementation()
    {
        $filelib = new FileLibrary();
        $dispatcher = $filelib->getEventDispatcher();
        
        $this->assertInstanceOf('Symfony\Component\EventDispatcher\EventDispatcher', $dispatcher);
        
    }
    
    
    /**
     * @test
     */
    public function getEventDispatcherShouldObeySetter()
    {
        $filelib = new FileLibrary();
        
        $mock = $this->getMockForAbstractClass('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        
        $this->assertSame($filelib, $filelib->setEventDispatcher($mock));
                
        $dispatcher = $filelib->getEventDispatcher();
        
        $this->assertSame($mock, $dispatcher);
        
    }
    
    
    
}