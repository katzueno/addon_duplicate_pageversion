<?php
namespace Concrete\Package\DuplicatePageVersionJob;

use Concrete\Core\Package\Package;
use Job;

class Controller extends Package
{
    protected $pkgHandle = 'duplicate_page_version_job';
    protected $appVersionRequired = '5.7.4.2';
    protected $pkgVersion = '0.8.9';
    protected $pkgAutoloaderMapCoreExtensions = true;

    public function getPackageDescription()
    {
        return t('This is a simple job package to duplicate page versions of all pages. This is to fix upgrade error from ver 5.7.x to ver 8.0.x');
    }

    public function getPackageName()
    {
        return t('Duplicate Page Version Job');
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installJobs($pkg);
    }
    public function upgrade()
    {
        $pkg = parent::upgrade();
        $this->installJobs($pkg);
    }

    protected function installJobs($pkg)
    {
        $jobHandle = 'duplicate_page_version';
        $job = Job::getByHandle($jobHandle);
        if (!is_object($job)) {
            Job::installByPackage($jobHandle, $pkg);
        }
    }
}
