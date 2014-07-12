<?php


namespace Net\Dontdrinkandroot\Gitki\BaseBundle\Service;

// TODO: make sure that file is in repository

use GitWrapper\GitWrapper;
use Net\Dontdrinkandroot\Gitki\BaseBundle\Event\PageSavedEvent;
use Net\Dontdrinkandroot\Gitki\BaseBundle\Exception\PageLockedException;
use Net\Dontdrinkandroot\Gitki\BaseBundle\Exception\PageLockExpiredException;
use Net\Dontdrinkandroot\Gitki\BaseBundle\Model\DirectoryListing;
use Net\Dontdrinkandroot\Gitki\BaseBundle\Model\Path;
use Net\Dontdrinkandroot\Gitki\BaseBundle\Security\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class WikiService
{

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $eventDispatcher;

    protected $repositoryPath;

    public function __construct($repositoryPath, EventDispatcherInterface $eventDispatcher)
    {
        if (empty($repositoryPath)) {
            throw new \Exception('Repository path must not be empty');
        }
        $this->repositoryPath = $repositoryPath;

        $this->eventDispatcher = $eventDispatcher;
    }

    public function pageExists(Path $path)
    {
        $absolutePath = $this->getAbsolutePath($path);
        return file_exists($absolutePath);
    }


    public function createLock(User $user, Path $path)
    {
        $lockPath = $this->getAbsoluteLockPath($path);

        $this->assertUnlocked($user, $lockPath);

        $fileSystem = new Filesystem();
        $lockDir = dirname($lockPath);
        if (!$fileSystem->exists($lockDir)) {
            $fileSystem->mkdir($lockDir, 0755);
        }

        if ($fileSystem->exists($lockPath)) {
            $fileSystem->touch($lockPath);
        } else {
            file_put_contents($lockPath, $user->getLogin());
        }
    }

    public function removeLock(User $user, Path $path)
    {
        $lockPath = $this->getAbsoluteLockPath($path);
        if (!file_exists($lockPath)) {
            return;
        }

        if ($this->isLockExpired($lockPath)) {
            return;
        }

        $lockLogin = $this->getLockLogin($lockPath);
        if ($lockLogin != $user->getLogin()) {
            throw new \Exception('Cannot remove lock of different user');
        }

        $this->removeLockFile($lockPath);
    }


    public function getContent(Path $path)
    {
        $absolutePath = $this->getAbsolutePath($path);
        if (!file_exists($absolutePath)) {
            return '';
        }

        return file_get_contents($absolutePath);
    }

    public function savePage(User $user, Path $path, $content, $commitMessage)
    {
        $lockPath = $this->getAbsoluteLockPath($path);
        $this->assertHasLock($user, $lockPath);

        $absolutePath = $this->getAbsolutePath($path);
        file_put_contents($absolutePath, $content);

        $workingCopy = $this->getWorkingCopy();
        $workingCopy->add($absolutePath);
        $workingCopy->commit(
            array(
                'm' => $commitMessage,
                'author' => $this->getAuthor($user)
            )
        );

        $this->eventDispatcher->dispatch(
            'ddr.gitki.wiki.page.saved',
            new PageSavedEvent($path, $user->getLogin(), time(), $content, $commitMessage)
        );
    }

    public function deletePage(User $user, Path $path)
    {
        $this->createLock($user, $path);

        $absolutePath = $this->getAbsolutePath($path);
        $workingCopy = $this->getWorkingCopy();
        $workingCopy->rm($absolutePath);
        $workingCopy->commit(
            array(
                'm' => 'Removing ' . $path->toString(),
                'author' => $this->getAuthor($user)
            )
        );

        $this->removeLock($user, $path);
    }

    public function assertUnlocked(User $user, $lockPath)
    {
        if (!file_exists($lockPath)) {
            return true;
        }

        if ($this->isLockExpired($lockPath)) {
            return true;
        }

        $lockLogin = $this->getLockLogin($lockPath);
        if ($lockLogin == $user->getLogin()) {
            return true;
        }

        throw new PageLockedException($lockLogin, $this->getLockExpiry($lockPath));
    }

    public function listDirectory(Path $path)
    {
        $absolutePath = $this->getAbsolutePath($path);
        $files = array();
        $subDirectories = array();

        $finder = new Finder();
        $finder->in($absolutePath);
        $finder->name('*.md');
        $finder->depth(0);
        foreach ($finder->files() as $file) {
            /* @var \Symfony\Component\Finder\SplFileInfo $file */
            $files[] = $path->addSegment($file->getRelativePathname());
        }

        $finder = new Finder();
        $finder->in($absolutePath);
        $finder->depth(0);
        $finder->ignoreDotFiles(true);
        foreach ($finder->directories() as $directory) {
            /* @var \Symfony\Component\Finder\SplFileInfo $directory */
            $subDirectories[] = $path->addSegment($directory->getRelativePathname());
        }

        return new DirectoryListing($path, $files, $subDirectories);
    }

    protected function assertHasLock(User $user, $lockPath)
    {
        if (file_exists($lockPath) && !$this->isLockExpired($lockPath)) {
            $lockLogin = $this->getLockLogin($lockPath);
            if ($lockLogin == $user->getLogin($user)) {
                return true;
            }
        }

        throw new PageLockExpiredException();
    }

    protected function removeLockFile($lockPath)
    {
        unlink($lockPath);
    }

    protected function getLockLogin($lockPath)
    {
        return file_get_contents($lockPath);
    }

    protected function getAbsolutePath(Path $path)
    {
        $absolutePath = $this->repositoryPath . '/' . $path->toString();
        return $absolutePath;
    }

    protected function getAbsoluteLockPath(Path $path)
    {
        $lockPath = $path->getParentPath()->addSegment($path->getName() . '.lock');
        $absoluteLockPath = $this->repositoryPath . '/' . $lockPath->toString();

        return $absoluteLockPath;
    }

    protected function isLockExpired($lockPath)
    {
        $expired = time() > $this->getLockExpiry($lockPath);
        if ($expired) {
            $this->removeLockFile($lockPath);
        }

        return $expired;
    }

    protected function getLockExpiry($lockPath)
    {
        $mTime = filemtime($lockPath);
        return $mTime + (60 * 5);
    }

    protected function getAuthor(User $user)
    {
        $name = $user->getLogin();
        if (null != $user->getRealName()) {
            $name = $user->getRealName();
        }
        $email = $user->getPrimaryEMail();

        return "\"$name <$email>\"";
    }

    /**
     * @return \GitWrapper\GitWorkingCopy
     */
    protected function getWorkingCopy()
    {
        $git = new GitWrapper();
        $workingCopy = $git->workingCopy($this->repositoryPath);

        return $workingCopy;
    }


}