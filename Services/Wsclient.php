<?php
/*************************************************************************************************
 * Copyright 2015 JPL TSolucio, S.L. -- This file is a part of TSOLUCIO coreBOS Customizations.
 * Licensed under the vtiger CRM Public License Version 1.1 (the "License"); you may not use this
 * file except in compliance with the License. You can redistribute it and/or modify it
 * under the terms of the License. JPL TSolucio, S.L. reserves all rights not expressly
 * granted by the License. coreBOS distributed by JPL TSolucio S.L. is distributed in
 * the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Unless required by
 * applicable law or agreed to in writing, software distributed under the License is
 * distributed on an "AS IS" BASIS, WITHOUT ANY WARRANTIES OR CONDITIONS OF ANY KIND,
 * either express or implied. See the License for the specific language governing
 * permissions and limitations under the License. You may obtain a copy of the License
 * at <http://corebos.org/documentation/doku.php?id=en:devel:vpl11>
 *************************************************************************************************/

namespace MauticPlugin\MauticCorebosBundle\Services;

use Mautic\PluginBundle\Helper\IntegrationHelper;

global $coreBOS_Basedir;
if (empty($coreBOS_Basedir)) {
    $coreBOS_Basedir = __DIR__;
}
require_once $coreBOS_Basedir.'/Net/HTTP_Client.php';

/**
 * Vtiger Webservice Client.
 */
class Wsclient
{
    // Webserice file
    private $_servicebase = 'webservice.php';

    // HTTP Client instance
    public $_client = false;
    // Service URL to which client connects to
    public $_serviceurl = false;

    // Webservice user credentials
    public $_serviceuser = false;
    public $_servicekey = false;

    // Webservice login validity
    public $_servertime = false;
    public $_expiretime = false;
    public $_servicetoken = false;

    // Webservice login credentials
    public $_sessionid = false;
    public $_userid = false;

    // Last operation error information
    public $_lasterror = false;

    // Version
    public $wsclient_version = 'coreBOS2.1';

    // Company Name
    public $company_name = '';

    /**
     * Constructor.
     */
    public function __construct(IntegrationHelper $integrationHelper)
    {
        $integrationObject = $integrationHelper->getIntegrationObject('Corebos');
        $keys = $integrationObject->getDecryptedApiKeys();
        $master_url = isset($keys['corebos_url']) ? $keys['corebos_url'] : '';
        $master_username = isset($keys['corebos_username']) ? $keys['corebos_username'] : '';
        $master_key = isset($keys['corebos_accesskey']) ? $keys['corebos_accesskey'] : '';
        $this->company_name = isset($keys['corebos_company_name']) ? $keys['corebos_company_name'] : '';
        $this->_serviceurl = $this->getWebServiceURL($master_url);
        $this->_client = new cbHTTP_Client($this->_serviceurl);
        $this->doLogin($master_username, $master_key);
    }

    /**
     * Set url.
     */
    public function setUrl($url)
    {
        $this->_serviceurl = $this->getWebServiceURL($url);
        $this->_client = new cbHTTP_Client($this->_serviceurl);
    }

    /**
     * Set url.
     */
    public function setCredentials($user, $password, $url)
    {
        $this->_serviceurl = $this->getWebServiceURL($url);
        $this->_client = new cbHTTP_Client($this->_serviceurl);

        $this->doLogin($user, $password);
    }

    /**
     * Return the client library version.
     */
    public function version()
    {
        return $this->wsclient_version;
    }

    /**
     * Reinitialize the client.
     */
    public function reinitalize()
    {
        $this->_client = new cbHTTP_Client($this->_serviceurl);
    }

    /**
     * Get the URL for sending webservice request.
     */
    public function getWebServiceURL($url)
    {
        if (false === stripos($url, $this->_servicebase)) {
            if (strrpos($url, '/') != (strlen($url) - 1)) {
                $url .= '/';
            }
            $url .= $this->_servicebase;
        }

        return $url;
    }

    /**
     * Get actual record id from the response id.
     */
    public function getRecordId($id)
    {
        $ex = explode('x', $id);

        return $ex[1];
    }

    /**
     * Check if result has any error.
     */
    public function hasError($result)
    {
        if (is_array($result) && isset($result['success']) && true === $result['success']) {
            $this->_lasterror = false;

            return false;
        }
        $this->_lasterror = isset($result['error']) ? $result['error'] : $result;

        return true;
    }

    /**
     * Get last operation error.
     */
    public function lastError()
    {
        return $this->_lasterror;
    }

    /**
     * Perform the challenge.
     */
    private function __doChallenge($username)
    {
        $getdata = [
            'operation' => 'getchallenge',
            'username' => $username,
        ];
        $resultdata = $this->_client->doGet($getdata, true);

        if ($this->hasError($resultdata)) {
            return false;
        }

        $this->_servertime = $resultdata['result']['serverTime'];
        $this->_expiretime = $resultdata['result']['expireTime'];
        $this->_servicetoken = $resultdata['result']['token'];

        return true;
    }

    /**
     * Check and perform login if requried.
     */
    private function __checkLogin()
    {
        /*if($this->_expiretime || (time() > $this->_expiretime)) {
            $this->doLogin($this->_serviceuser, $this->_servicepwd);
        }*/
    }

    /**
     * Do Login Operation.
     */
    public function doLogin($username, $userAccesskey, $withpassword = false)
    {
        // Do the challenge before login
        if (false === $this->__doChallenge($username)) {
            return false;
        }

        $postdata = [
            'operation' => 'login',
            'username' => $username,
            'accessKey' => ($withpassword ? $this->_servicetoken.$userAccesskey : md5($this->_servicetoken.$userAccesskey)),
        ];
        $resultdata = $this->_client->doPost($postdata, true);

        if ($this->hasError($resultdata)) {
            return false;
        }
        $this->_serviceuser = $username;
        $this->_servicekey = $userAccesskey;

        $this->_sessionid = $resultdata['result']['sessionName'];
        $this->_userid = $resultdata['result']['userId'];

        return true;
    }

    /**
     * Do Logout Operation.
     */
    public function doLogout()
    {
        $this->__checkLogin();
        $postdata = [
            'operation' => 'logout',
            'sessionName' => $this->_sessionid,
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Do Query Operation.
     */
    public function doQuery($query)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        // Make sure the query ends with ;
        $query = trim($query);
        if (strrpos($query, ';') != strlen($query) - 1) {
            $query .= ';';
        }

        $getdata = [
            'operation' => 'query',
            'sessionName' => $this->_sessionid,
            'query' => $query,
        ];
        $resultdata = $this->_client->doGet($getdata, true);

        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Get Result Column Names.
     */
    public function getResultColumns($result)
    {
        $columns = [];
        if (!empty($result)) {
            $firstrow = $result[0];
            foreach ($firstrow as $key => $value) {
                $columns[] = $key;
            }
        }

        return $columns;
    }

    /**
     * List types available Modules.
     */
    public function doListTypes($fieldTypeList = '')
    {
        // Perform re-login if required.
        $this->__checkLogin();

        if (is_array($fieldTypeList)) {
            $fieldTypeList = json_encode($fieldTypeList);
        }
        $getdata = [
            'operation' => 'listtypes',
            'sessionName' => $this->_sessionid,
            'fieldTypeList' => $fieldTypeList,
        ];
        $resultdata = $this->_client->doGet($getdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }
        $modulenames = $resultdata['result']['types'];

        $returnvalue = [];
        foreach ($modulenames as $modulename) {
            $returnvalue[$modulename] = ['name' => $modulename];
        }

        return $returnvalue;
    }

    /**
     * Describe Module Fields.
     */
    public function doDescribe($module)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        $getdata = [
            'operation' => 'describe',
            'sessionName' => $this->_sessionid,
            'elementType' => $module,
        ];
        $resultdata = $this->_client->doGet($getdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Retrieve details of record.
     */
    public function doRetrieve($record)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        $getdata = [
            'operation' => 'retrieve',
            'sessionName' => $this->_sessionid,
            'id' => $record,
        ];
        $resultdata = $this->_client->doGet($getdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Do Create Operation.
     */
    public function doCreate($module, $valuemap)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        // Assign record to logged in user if not specified
        if (!isset($valuemap['assigned_user_id'])) {
            $valuemap['assigned_user_id'] = $this->_userid;
        }

        $postdata = [
            'operation' => 'create',
            'sessionName' => $this->_sessionid,
            'elementType' => $module,
            'element' => json_encode($valuemap),
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    public function doUpdate($module, $valuemap)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        // Assign record to logged in user if not specified
        if (!isset($valuemap['assigned_user_id'])) {
            $valuemap['assigned_user_id'] = $this->_userid;
        }

        $postdata = [
            'operation' => 'update',
            'sessionName' => $this->_sessionid,
            'elementType' => $module,
            'element' => json_encode($valuemap),
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    public function doRevise($module, $valuemap)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        // Assign record to logged in user if not specified
        if (!isset($valuemap['assigned_user_id'])) {
            $valuemap['assigned_user_id'] = $this->_userid;
        }

        $postdata = [
            'operation' => 'revise',
            'sessionName' => $this->_sessionid,
            'elementType' => $module,
            'element' => json_encode($valuemap),
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Do Delete Operation.
     */
    public function doDelete($record)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        $postdata = [
            'operation' => 'delete',
            'sessionName' => $this->_sessionid,
            'id' => $record,
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Do Upsert Operation.
     */
    public function doUpsert($modulename, $createFields, $searchOn, $updateFields)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        // Assign record to logged in user if not specified
        if (!isset($createFields['assigned_user_id'])) {
            $createFields['assigned_user_id'] = $this->_userid;
        }

        $postdata = [
            'operation' => 'upsert',
            'sessionName' => $this->_sessionid,
            'elementType' => $modulename,
            'element' => json_encode($createFields),
            'searchOn' => $searchOn,
            'updatedfields' => implode(',', $updateFields),
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Invoke custom operation.
     *
     * @param string $method Name of the webservice to invoke
     * @param object $type   null or parameter values to method
     * @param string $params optional (POST/GET)
     */
    public function doInvoke($method, $params = null, $type = 'POST')
    {
        // Perform re-login if required
        $this->__checkLogin();

        $senddata = [
            'operation' => $method,
            'sessionName' => $this->_sessionid,
        ];
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                if (!isset($senddata[$k])) {
                    $senddata[$k] = $v;
                }
            }
        }

        $resultdata = false;
        if ('POST' == strtoupper($type)) {
            $resultdata = $this->_client->doPost($senddata, true);
        } else {
            $resultdata = $this->_client->doGet($senddata, true);
        }

        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }

    /**
     * Retrieve related records.
     */
    public function doGetRelatedRecords($record, $module, $relatedModule, $queryParameters)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        $postdata = [
            'operation' => 'getRelatedRecords',
            'sessionName' => $this->_sessionid,
            'id' => $record,
            'module' => $module,
            'relatedModule' => $relatedModule,
            'queryParameters' => $queryParameters,
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result']['records'];
    }

    /**
     * Set relation between records.
     * param relate_this_id string ID of record we want to related other records with
     * param with_this_ids string/array either a string with one unique ID or an array of IDs to relate to the first parameter.
     */
    public function doSetRelated($relate_this_id, $with_these_ids)
    {
        // Perform re-login if required.
        $this->__checkLogin();

        $postdata = [
            'operation' => 'SetRelation',
            'sessionName' => $this->_sessionid,
            'relate_this_id' => $relate_this_id,
            'with_these_ids' => json_encode($with_these_ids),
        ];
        $resultdata = $this->_client->doPost($postdata, true);
        if ($this->hasError($resultdata)) {
            return false;
        }

        return $resultdata['result'];
    }
}
