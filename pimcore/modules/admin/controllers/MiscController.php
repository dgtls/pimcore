<?php
/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @copyright  Copyright (c) 2009-2010 elements.at New Media Solutions GmbH (http://www.elements.at)
 * @license    http://www.pimcore.org/license     New BSD License
 */

class Admin_MiscController extends Pimcore_Controller_Action_Admin
{

    public function liveconnectAction()
    {

        $token = $this->_getParam("token");
        Pimcore_Liveconnect::setToken($token);
        $this->view->token = $token;
    }

    public function jsonTranslationsAdminAction()
    {
        $this->getResponse()->setHeader("Content-Type", "text/javascript", true);

        $language = $this->_getParam("language");

        $list = new Translation_Admin_List();
        $list->setOrder("asc");
        $list->setOrderKey("key");
        $list->load();

        $translations = array();
        foreach ($list->getTranslations() as $t) {
            $translations[$t->getKey()] = $t->getTranslation($language);
        }
        $this->view->translations = $translations;
    }

    public function jsonTranslationsSystemAction()
    {
        $this->getResponse()->setHeader("Content-Type", "text/javascript", true);

        $language = $this->_getParam("language");

        $languageFile = Pimcore_Tool_Admin::getLanguageFile($language);
        if (!is_file($languageFile)) {
            $languageFile = Pimcore_Tool_Admin::getLanguageFile("en");
        }

        $row = 1;
        $handle = fopen($languageFile, "r");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $translations[$data[0]] = $data[1];
        }
        fclose($handle);

        $broker = Pimcore_API_Plugin_Broker::getInstance();
        $pluginTranslations = $broker->getTranslations($language);
        //$pluginTranslations = $this->getApiPluginBroker()->getTranslations($language);
        $translations = array_merge($pluginTranslations, $translations);

        $this->view->translations = $translations;
    }

    public function scriptProxyAction()
    {
        $this->removeViewRenderer();

        $scripts = explode(",", $this->_getParam("scripts"));
        $scriptPath = $this->_getParam("scriptPath");
        $scriptsContent = "";

        foreach ($scripts as $script) {
            $filePath = PIMCORE_DOCUMENT_ROOT . $scriptPath . $script;
            if (is_file($filePath) && is_readable($filePath)) {
                $scriptsContent .= file_get_contents($filePath);
            }
        }

        header("Cache-Control: max-age=86400");
        header("Pragma: ");
        //header("Content-Length: ".strlen($scriptsContent));
        header("Content-Type: application/x-javascript");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");
        echo $scriptsContent;
    }

    public function adminCssAction()
    {
        // customviews config
        $cvData = Pimcore_Tool::getCustomViewConfig();
        $this->view->customviews = $cvData;


        $this->getResponse()->setHeader("Content-Type", "text/css; charset=UTF-8", true);
    }

    public function pingAction()
    {

        $response = array(
            "success" => true
        );


        $this->_helper->json($response);
    }

    public function lockElementAction()
    {
        Element_Editlock::lock($this->_getParam("id"), $this->_getParam("type"));
        exit;
    }

    public function unlockElementAction()
    {
        Element_Editlock::unlock($this->_getParam("id"), $this->_getParam("type"));
        exit;
    }

    public function availableLanguagesAction()
    {
        $this->getResponse()->setHeader("Content-Type", "text/javascript", true);

        $languages = Zend_Locale::getTranslationList('language');


        asort($languages);
        $languageOptions = array();
        $validLanguages = array();
        foreach ($languages as $short => $translation) {

            if (strlen($short) == 2 or (strlen($short) == 5 and strpos($short, "_") == 2)) {
                $languageOptions[$short] = $translation;
            }
        }

        $this->view->languages = $languageOptions;
    }

    public function getValidFilenameAction () {
        $this->_helper->json(array(
            "filename" => Pimcore_File::getValidFilename($this->_getParam("value"))
        ));
    }

    /* FILEEXPLORER */

    public function fileexplorerTreeAction()
    {

        $path = preg_replace("/^\/fileexplorer/", "", $this->_getParam("node"));
        $referencePath = PIMCORE_DOCUMENT_ROOT . $path;

        $items = scandir($referencePath);
        $contents = array();

        foreach ($items as $item) {

            if ($item == "." || $item == "..") {
                continue;
            }

            $file = $referencePath . "/" . $item;
            $file = str_replace("//", "/", $file);

            if (is_dir($file) || is_file($file)) {
                $itemConfig = array(
                    "id" => "/fileexplorer" . str_replace(PIMCORE_DOCUMENT_ROOT, "", $file),
                    "text" => $item,
                    "leaf" => true,
                    "writeable" => is_writable($file)
                );

                if (is_dir($file)) {
                    $itemConfig["leaf"] = false;
                    $itemConfig["type"] = "folder";
                } else if (is_file($file)) {
                    $itemConfig["type"] = "file";
                }

                $contents[] = $itemConfig;
            }
        }

        $this->_helper->json($contents);
    }

    public function fileexplorerContentAction()
    {

        $success = false;
        $writeable = false;
        $path = preg_replace("/^\/fileexplorer/", "", $this->_getParam("path"));
        $file = PIMCORE_DOCUMENT_ROOT . $path;
        if (is_file($file)) {
            if (is_readable($file)) {
                $content = file_get_contents($file);
                $success = true;
                $writeable = is_writeable($file);
            }
        }

        $this->_helper->json(array(
                                  "success" => $success,
                                  "content" => $content,
                                  "writeable" => $writeable,
                                  "path" => $path
                             ));
    }

    public function fileexplorerContentSaveAction()
    {

        $success = false;

        if ($this->_getParam("content") && $this->_getParam("path")) {
            $file = PIMCORE_DOCUMENT_ROOT . $this->_getParam("path");
            if (is_file($file) && is_writeable($file)) {
                file_put_contents($file, $this->_getParam("content"));
                chmod($file, 0766);

                $success = true;
            }
        }

        $this->_helper->json(array(
                                  "success" => $success
                             ));
    }

    public function fileexplorerAddAction()
    {
        $success = false;

        if ($this->_getParam("filename") && $this->_getParam("path")) {
            $path = preg_replace("/^\/fileexplorer/", "", $this->_getParam("path"));
            $file = PIMCORE_DOCUMENT_ROOT . $path . "/" . $this->_getParam("filename");

            if (is_writeable(dirname($file))) {
                file_put_contents($file, "");
                chmod($file, 0766);

                $success = true;
            }
        }

        $this->_helper->json(array(
                                  "success" => $success
                             ));
    }

    public function fileexplorerAddFolderAction()
    {
        $success = false;

        if ($this->_getParam("filename") && $this->_getParam("path")) {
            $path = preg_replace("/^\/fileexplorer/", "", $this->_getParam("path"));
            $file = PIMCORE_DOCUMENT_ROOT . $path . "/" . $this->_getParam("filename");

            if (is_writeable(dirname($file))) {
                mkdir($file);

                $success = true;
            }
        }

        $this->_helper->json(array(
                                  "success" => $success
                             ));
    }

    public function fileexplorerDeleteAction()
    {

        if ($this->_getParam("path")) {
            $path = preg_replace("/^\/fileexplorer/", "", $this->_getParam("path"));
            $file = PIMCORE_DOCUMENT_ROOT . $path;
            if (is_writeable($file)) {
                unlink($file);
                $success = true;
            }
        }

        $this->_helper->json(array(
                                  "success" => $success
                             ));
    }

    public function maintenanceAction()
    {
        if ($this->_getParam("activate")) {
            Pimcore_Tool_Admin::activateMaintenanceMode();
        }

        if ($this->_getParam("deactivate")) {
            Pimcore_Tool_Admin::deactivateMaintenanceMode();
        }

        $this->_helper->json(array(
                                  "success" => true
                             ));
    }

    public function httpErrorLogAction() {

        $db = Pimcore_Resource::get();

        $limit = $this->_getParam("limit");
        $offset = $this->_getParam("start");
        $sort = $this->_getParam("sort");
        $dir = $this->_getParam("dir");
        $filter = $this->_getParam("filter");
        if(!$limit) {
            $limit = 20;
        }
        if(!$offset) {
            $offset = 0;
        }
        if(!$sort || !in_array($dir, array("id","code","path","date"))) {
            $sort = "date";
        }
        if(!$dir || !in_array($dir, array("DESC","ASC"))) {
            $dir = "DESC";
        }

        $condition = "";
        if($filter) {
            $condition = " WHERE path LIKE " . $db->quote("%" . $filter . "%");
        }

        $logs = $db->fetchAll("SELECT id,code,path,date,count(*) as amount,concat(code,path) as `group` FROM http_error_log " . $condition . " GROUP BY `group` ORDER BY " . $sort . " " . $dir . " LIMIT " . $offset . "," . $limit);
        $total = $db->fetchOne("SELECT count(*) FROM (SELECT concat(code,path) as `group` FROM http_error_log " . $condition . " GROUP BY `group`) as counting");

        $this->_helper->json(array(
            "items" => $logs,
            "total" => $total,
            "success" => true
        ));
    }

    public function httpErrorLogFlushAction() {

        $db = Pimcore_Resource::get();
        $db->delete("http_error_log");

        $this->_helper->json(array(
            "success" => true
        ));
    }

    public function phpinfoAction()
    {
        phpinfo();
        exit;
    }

    public function testAction()
    {

        die("done");
    }
}

