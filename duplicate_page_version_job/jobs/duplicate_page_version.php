<?php

namespace Concrete\Package\DuplicatePageVersionJob\Job;

use QueueableJob;
use ZendQueue\Queue as ZendQueue;
use ZendQueue\Message as ZendQueueMessage;
use Exception;
use Core;
use Concrete\Core\Page\PageList;
use Concrete\Core\Page\Page;
use User;
use UserInfo;
use PageEditResponse;
use Concrete\Core\Page\Collection\Version\EditResponse as PageEditVersionResponse;
use CollectionVersion;
use Concrete\Core\Form\Service\Widget\DateTime;
use Concrete\Core\Workflow\Request\ApprovePageRequest as ApprovePagePageWorkflowRequest;

class DuplicatePageVersion extends QueueableJob
{
    public $jSupportsQueue = true;
    protected $page;

    public function getJobName()
    {
        return t('Duplicate Page Version');
    }

    public function getJobDescription()
    {
        return t('This is a simple job package to duplicate page versions of all pages. This is to fix upgrade error from ver 5.7.x to ver 8.0.x.');
    }

    public function start(ZendQueue $q)
    {
        $pl = new PageList;
        $pl->ignorePermissions();
        $pages = $pl->getResults();
        foreach ($pages as $page) {
            $q->send($page->getCollectionID());
        }
    }

    public function processQueueItem(ZendQueueMessage $msg)
    {
        try {
            $u = User::getByUserID(1);
            $ui = UserInfo::getByID($u->getUserID());
            $page = Page::getByID($msg->body);
            $this->page = $page;
            $comments = 'Duplicate Page Version Job';
            $comments = is_string($comments) ? trim($comments) : '';
            $c = $page;
            if ($c->isPageDraft()) {
                return;
            }

            $cDescription = $page->getCollectionDescription();
            $nc = $page->cloneVersion(t('Duplicate Page Version Job: %s', $page->getVersionID()));

            /*
            $randomString = "XCiLbmyWGnxVqwF2QZyHX1zZR3RUGdvLpruDKiy8dSTG8pP7eWMZXYlLTHD84XeN";

            $u->loadCollectionEdit($page);
            $nvc = $page->getVersionToModify();
            $data = array();
            $data['cDescription'] = $cDescription . "+" . $randomString;
            $nvc->update($data);
            $u->unloadCollectionEdit($page);
            */

            $u->loadCollectionEdit($page);
            $nvc = $page->getVersionToModify();
            $data = array();
            $data['cDescription'] = $cDescription;
            $nvc->update($data);
            $u->unloadCollectionEdit($page);

            $v = $nc->getVersionObject();
            $pr = new PageEditResponse();
            $e = $this->checkForPublishing();
            $pr->setError($e);
            if (!$e->has()) {
                $r = new PageEditVersionResponse();
                $r->addCollectionVersion($v);
                $pkr = new ApprovePagePageWorkflowRequest();
                $pkr->setRequestedPage($c);
                $pkr->setRequestedVersionID($v->getVersionID());
                $pkr->setRequesterUserID($u->getUserID());
                $u->unloadCollectionEdit($c);

                $pkr->trigger();

                $ov = CollectionVersion::get($c, 'ACTIVE');
                if (is_object($ov)) {
                    $ovID = $ov->getVersionID();
                }
                if ($ovID) {
                    $r->addCollectionVersion(CollectionVersion::get($c, $ovID));
                }
            }
        } catch (Exception $e) {
            throw new Exception(t('Error occurred while getting the Page object of pID: %s', $msg->body));
        }
    }

    protected function checkForPublishing()
    {
        $c = $this->page;
        // verify this page type has all the items necessary to be approved.
        $e = Core::make('helper/validation/error');
        if ($c->isPageDraft()) {
            if (!$c->getPageDraftTargetParentPageID()) {
                $e->add(t('You haven\'t chosen where to publish this page.'));
            }
        }
        $pagetype = $c->getPageTypeObject();
        if (is_object($pagetype)) {
            $validator = $pagetype->getPageTypeValidatorObject();
            $e->add($validator->validatePublishDraftRequest($c));
        }

        if ($c->isPageDraft() && !$e->has()) {
            $targetParentID = $c->getPageDraftTargetParentPageID();
            if ($targetParentID) {
                $tp = Page::getByID($targetParentID, 'ACTIVE');
                $pp = new Permissions($tp);
                if (!is_object($tp) || $tp->isError()) {
                    $e->add(t('Invalid target page.'));
                } else {
                    if (!$pp->canAddSubCollection($pagetype)) {
                        $e->add(
                            t(
                                'You do not have permissions to add a page of this type in the selected location.'
                            )
                        );
                    }
                }
            }
        }

        return $e;
    }

    public function finish(ZendQueue $q)
    {
        return t('Finished duplicating page version of all pages.');
    }
}
