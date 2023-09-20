<?php
namespace Migration\DAO;

define("OTHER", 9999);

class MigrateDAO {

    private $PDOX;
    private $p;
    private $tool;
    public static $clickup_option_id; //This refers to a specific clickup column

    public function __construct($PDOX, $p, $tool) {
        $this->PDOX = $PDOX;
        $this->p = $p;
        $this->tool = $tool;
        if (isset($this->tool['clickup_option_Id'])) {
            self::$clickup_option_id = $this->tool['clickup_option_Id'];
        }
    }

    function getMigration($link_id, $user_id, $site_id, $provider, $is_admin, $title) {

        $arr = array(':linkId' => $link_id, ':siteId' => $site_id, ':title' => $title);
        $this->PDOX->queryDie("Update {$this->p}migration_site set title = :title where link_id = :linkId and site_id = :siteId;", $arr);

        $arr = array(':linkId' => $link_id, ':siteId' => $site_id);
        $query = "SELECT
            `site`.notification as `notification`,
            `migration`.created_at, `site`.started_at, `site`.modified_at,
            ifnull(`user`.displayname,'') as displayname, ifnull(`user`.email,'') as email,
            ifnull(`site`.report_url,'') as report_url,
            `site`.state, `site`.`active`, `site`.workflow, `migration`.is_admin,
            `site`.imported_site_id,
            `site`.transfer_site_id,
            `site`.target_site_id,
            ifnull(`site`.`provider`, '') as `provider`,
            ifnull(`site`.`term`, 0) as `term`,
            ifnull(`site`.`dept`, '') as `dept`,
            target_title, target_course, target_term, target_dept, create_course_offering
            FROM {$this->p}migration `migration`
            left join {$this->p}migration_site `site` on `site`.link_id = `migration`.link_id
            left join {$this->p}lti_user `user` on `user`.user_id = `site`.started_by
            WHERE `migration`.link_id = :linkId and `site`.site_id = :siteId and `site`.state is not null limit 1;";

        if ($is_admin) {
            $query = "SELECT `migration`.created_at, `migration`.is_admin, `site`.state,
                            '' as `email`, '' as `displayname`, `site`.notification as `notification`,
                            ifnull(`site`.`provider`, '') as `provider`,
                            ifnull(`site`.`term`, 0) as `term`,
                            ifnull(`site`.`dept`, '') as `dept`
                FROM {$this->p}migration `migration`
                left join {$this->p}migration_site `site` on `site`.link_id = `migration`.link_id
                WHERE `migration`.link_id = :linkId and `site`.site_id = :siteId limit 1;";
            // unset($arr[':siteId']);
        }

        $rows = $this->PDOX->rowDie($query, $arr);

        if (gettype($rows) == "boolean") {
            if ($this->createEmpty($link_id, $user_id, $site_id, $provider, $is_admin)) {
                return $this->getMigration($link_id, $user_id, $site_id, $provider, $is_admin, $title);
            }
        }

        return $rows;
    }

    function createEmpty($link_id, $user_id, $site_id, $provider, $is_admin) {
        $this->PDOX->queryDie("REPLACE INTO {$this->p}migration
                    (link_id, user_id, created_at, created_by, is_admin)
                    VALUES (:linkId, :userId, NOW(), :userId, :isAdmin)",
                array(':linkId' => $link_id, ':userId' => $user_id, ':isAdmin' => $is_admin ? b'1' : b'0'));

        $this->PDOX->queryDie("REPLACE INTO {$this->p}migration_site
                (link_id, site_id, modified_at, modified_by, provider, state)
                VALUES (:linkId, :siteId, NOW(), :userId, :provider, :state)",
            array(':linkId' => $link_id, ':siteId' => $site_id, ':userId' => $user_id, ':provider' => $is_admin ? b'1' : b'0',
                    ':state' => $is_admin ? 'admin' : 'init' ));

        return true;
    }

    function getMigrationsPerLinkStats($link_id) {

        $query = "SELECT `site`.`state`, count(*) as n
            FROM {$this->p}migration_site `site`
            where `site`.link_id = :linkId
            group by `state`
            having `site`.state <> 'admin';";

        $arr = array(':linkId' => $link_id);
        return $this->PDOX->allRowsDie($query, $arr);
    }

    function getMigrationsPerLink($link_id) {

        $query = "SELECT `site`.site_id, `site`.title, `site`.state, `site`.imported_site_id, `site`.modified_at, target_site_id,
            ifnull(`site`.report_url,'') as report_url,
            `site`.test_conversion, `site`.enrol_users, `site`.lesson_type
            FROM {$this->p}migration_site `site`
            where `site`.link_id = :linkId
            having `site`.state <> 'admin';";

        $arr = array(':linkId' => $link_id);
        return $this->PDOX->allRowsDie($query, $arr);
    }

    function setAdmin($link_id, $user_id, $site_id) {

        $this->PDOX->queryDie("UPDATE {$this->p}migration SET `is_admin` = 1 " .
                                "WHERE `link_id` = :linkId;",
                                array(':linkId' => $link_id));

        $this->PDOX->queryDie("UPDATE {$this->p}migration_site SET `state` = 'admin' " .
                                "WHERE `link_id` = :linkId and `site_id` = :siteId;",
                                array(':linkId' => $link_id, ':siteId' => $site_id));
    }

    function removeSite($link_id, $user_id, $site_id) {
        return $this->PDOX->queryDie("DELETE FROM {$this->p}migration_site " .
                                "WHERE `link_id` = :linkId and `site_id` = :siteId;",
                                array(':linkId' => $link_id, ':siteId' => $site_id));
    }

    function startMigration($link_id, $user_id, $site_id, $notifications, $dept, $term, $provider, $is_test, $enrol_users, $lesson_type,
                                $target_title, $target_course, $target_term, $target_dept, $create_course_offering, $task_id = null) {
        
        $now = date("Y-m-d H:i:s");

        $user_details = $this->PDOX->rowDie("SELECT user_id, displayname,email FROM {$this->p}lti_user WHERE user_id = :userid;",
                                            array(':userid' => $user_id));

        $user_name = $user_details['displayname'];
        $user_email = $user_details['email'];

        $workflow = ["$now,000 INFO Migration for site $site_id started by $user_name ($user_email)","$now,001 INFO Scheduled Export..."];

        if ($term == 'other') {
            $term = OTHER;
            $target_term = OTHER;
        }

        # Add check for size before starting workflow ...
        $set_state_to = 'starting';
        $site_size = $this->getSiteSize($site_id);
        if (!$site_size['site_can_migrate']) {
            $set_state_to = 'paused';
            $workflow = ["$now: ". $site_size['size_result_st']];
        }

        $query = "REPLACE INTO {$this->p}migration_site
                    (site_id, link_id, modified_at, modified_by, started_at, started_by, uploaded_at,
                    active, state, workflow, notification, term, provider, dept, report_url, files,
                    imported_site_id, transfer_site_id, test_conversion, enrol_users, lesson_type,
                    target_title, target_course, target_term, target_dept, create_course_offering)
                VALUES
                (:siteId, :linkId, NOW(), :userId, NOW(), :userId, NULL,
                1, :state, :workflow, :notifications, :term, :provider, :dept, NULL, NULL,
                0, NULL, :is_test, :enrol_users, :lesson_type,
                :target_title, :target_course, :target_term, :target_dept, :create_course_offering);";

        $arr = array(':linkId' => $link_id, ':siteId' => $site_id, ':userId' => $user_id, ':state' => $set_state_to,
                        ':term' => $term, ':provider' => '[]', ':dept' => $dept,
                        ':is_test' => $is_test ? 1 : 0, ':enrol_users' => $enrol_users ? 1 : 0, ':lesson_type' => $lesson_type,
                        ':target_title' => $target_title, ':target_course' => $target_course,
                        ':target_term' => $target_term, ':target_dept' => $target_dept, ':create_course_offering' => $create_course_offering,
                        ':notifications' => $notifications, ':workflow' => json_encode($workflow));
        $result = $this->PDOX->queryDie($query, $arr);

        if($result) {
            $updateTask_result = $this->updateTask($task_id);
            return $updateTask_result;
        }

        return $result;
    }

    function resetMigration($link_id, $user_id, $state) {

        $query = "UPDATE {$this->p}migration_site
        SET modified_at = NOW(), modified_by = :userId, `state` = :state, `active` = 0
        WHERE link_id = :linkId";

        $arr = array(':linkId' => $link_id, ':userId' => $user_id, ':state' => $state);
        return $this->PDOX->queryDie($query, $arr);
    }

    function updateMigration($link_id, $user_id, $notifications, $term) {
        $is_admin = FALSE; // Update all records at the same time

        // $is_admin = $this->PDOX->rowDie("SELECT is_admin FROM {$this->p}migration where link_id = :linkId limit 1;",
        //                                     array(':linkId' => $link_id));

        // if (gettype($is_admin) == "boolean") {
        //     $is_admin = FALSE;
        // } else {
        //     $is_admin = $is_admin['is_admin'] === 1;
        // }

        $query = "UPDATE {$this->p}migration_site
                SET modified_at = NOW(), modified_by = :userId, notification = :notifications, term = :term
                WHERE link_id = :linkId " . ($is_admin ? " and state = 'admin' " : "") .";";

        $arr = array(':linkId' => $link_id, ':userId' => $user_id, ':notifications' => $notifications, ':term' => $term);
        return $this->PDOX->queryDie($query, $arr);
    }

    function addSitesMigration($link_id, $user_id, $sites, $term, $is_test, $enrol_users, $lesson_type) {

        $notifications = $this->PDOX->rowDie("SELECT notification FROM {$this->p}migration_site where state = 'admin' and link_id = :linkId limit 1;",
                                            array(':linkId' => $link_id));

        if (gettype($notifications) == "boolean") {
            $notifications = '';
        } else {
            $notifications = $notifications['notification'];
        }

        $result = [];
        foreach ($sites as $site) {

            if (strlen($site) > 3) {
                $output = $this->startMigration($link_id, $user_id, $site, $notifications, '', $term, '[]', $is_test, $enrol_users, $lesson_type, '', '', $term, '', 0) ? 1 : 0;
                array_push($result, $output);
            }
        }

        return $result;
    }

    function getWorkflow($link_id, $site_id) {
        $query = "SELECT workflow FROM {$this->p}migration_site where link_id = :linkId and site_id = :siteId;";
        $rows = $this->PDOX->rowDie($query, array(':siteId' => $site_id, ':linkId' => $link_id));

        return ($rows == 0 ? [] : $rows);
    }

    function getWorkflowAndReport($link_id, $site_id) {
        $query = "SELECT workflow,
                        ifnull(report_url,'') as report_url,`state`,
                        imported_site_id, transfer_site_id
                        FROM {$this->p}migration_site where link_id = :linkId and site_id = :siteId;";
        $rows = $this->PDOX->rowDie($query, array(':siteId' => $site_id, ':linkId' => $link_id));

        return ($rows == 0 ? [] : $rows);
    }

    #### SUPER ADMIN
    function getAdminSiteIDs() {
        $query = "SELECT `A`.link_id, `B`.site_id, ifnull(`B`.title, 'No Title') as title, `A`.created_at
            FROM {$this->p}migration `A`
            inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id and `B`.state = 'admin'
            where `A`.is_admin = 1 order by title, `A`.created_at asc;";

        $arr = array();
        return $this->PDOX->allRowsDie($query, $arr);
    }

    function getAdminSiteStatus() {
        $query = "SELECT `B`.link_id, `B`.state, count(*) as n
            FROM {$this->p}migration `A`
            inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id and `B`.state <> 'admin'
            where `A`.is_admin = 1
            group by `B`.link_id, `B`.state;";

        $arr = array();
        return $this->PDOX->allRowsDie($query, $arr);
    }

    function getSingleSiteStatus() {
        $query = "SELECT `B`.state, count(*) as n
            FROM {$this->p}migration `A`
            inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id and `B`.state <> 'admin'
            where `A`.is_admin = 0
            group by `B`.state;";

        $arr = array();
        return $this->PDOX->allRowsDie($query, $arr);
    }

    function getAllReports($id) {
        $query = "SELECT `site`.title, ifnull(`site`.report_url,'') as report_url, `site`.started_at, `site`.modified_at, `site`.state,
                        `site`.link_id, `site`.site_id, `site`.imported_site_id, `site`.transfer_site_id,
                        IF(`site`.transfer_site_id = :id, 1, 0)  as `is_found`
                    FROM {$this->p}migration_site `site`
                    WHERE (`site`.site_id = :id or `site`.transfer_site_id = :id)
                    order by `site`.modified_at desc";

        return $this->PDOX->allRowsDie($query, array(':id' => $id));
    }

    function getReportTID($tid) {
        $query = "SELECT `site`.report_url FROM {$this->p}migration_site `site`
                    WHERE (`site`.transfer_site_id = :tid)
                    limit 1";

        return $this->PDOX->rowDie($query, array(':tid' => $tid));
    }

    function getReportSID($lid, $sid) {
        $query = "SELECT `site`.report_url FROM {$this->p}migration_site `site`
                    WHERE `site`.link_id = :linkId and `site`.site_id = :siteId
                    limit 1";

        return $this->PDOX->rowDie($query, array(':linkId' => $lid, ':siteId' => $sid));
    }

    function getSingleSites() {
        $query = "SELECT `B`.*
            FROM {$this->p}migration `A`
            inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id and `B`.state <> 'admin'
            where `A`.is_admin = 0 order by title, `A`.created_at asc;";

        $arr = array();
        return $this->PDOX->allRowsDie($query, $arr);
    }

    function getSingleSitesByState($link_id, $state, $offset, $records_per_page) {
        settype($offset, 'integer');
        settype($records_per_page, 'integer');
        if($state == 'all') {
            $query = "SELECT ifnull(`B`.started_at, 'No start date') as started_at, `B`.site_id, `B`.imported_site_id, `B`.report_url, `B`.state, ifnull(`B`.title, 'No Title') as title
            FROM {$this->p}migration `A`
            inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id
            where `A`.is_admin = 0 order by title, `A`.created_at ASC LIMIT $offset, $records_per_page;";

            return $this->PDOX->allRowsDie($query);
        } else {
            $query = "SELECT ifnull(`B`.started_at, 'No start date') as started_at, `B`.site_id, `B`.imported_site_id, `B`.report_url, `B`.state, ifnull(`B`.title, 'No Title') as title
                FROM {$this->p}migration `A`
                inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id and `B`.state = :filter_state
                where `A`.is_admin = 0 order by title, `A`.created_at ASC LIMIT $offset, $records_per_page;";

            return $this->PDOX->allRowsDie($query, array(':filter_state' => $state));
        }
    }

    function getAllSingleSitesByState($link_id, $state) {
        if($state == 'all') {
            $query = "SELECT ifnull(`B`.started_at, 'No start date') as started_at, `B`.site_id, `B`.imported_site_id, `B`.report_url, `B`.state, ifnull(`B`.title, 'No Title') as title
            FROM {$this->p}migration `A`
            inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id
            where `A`.is_admin = 0 order by title, `A`.created_at ASC;";

            return $this->PDOX->allRowsDie($query);
        } else {
            $query = "SELECT ifnull(`B`.started_at, 'No start date') as started_at, `B`.site_id, `B`.imported_site_id, `B`.report_url, `B`.state, ifnull(`B`.title, 'No Title') as title
                FROM {$this->p}migration `A`
                inner join {$this->p}migration_site `B` on `B`.link_id = `A`.link_id and `B`.state = :filter_state
                where `A`.is_admin = 0 order by title, `A`.created_at ASC;";

            return $this->PDOX->allRowsDie($query, array(':filter_state' => $state));
        }
    }

    public static function formatBytes($size, $precision = 2) {
        if ($size <= 0) {
            return '0KB';
        }
        $base = log($size, 1024);
        $suffixes = array('', 'K', 'M', 'G', 'T');

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)] .'B';
    }

    public function getSiteSize($site_id) {
        $size_result = -1;
        $size_result_st = '';
        if ($this->tool['SOAP_active']) {
            try {
                $login_client = new \SoapClient($this->tool['SOAP_url'] . '/sakai-ws/soap/login?wsdl');//sakai-ws/soap/uct?wsdl');
                $session_array = explode(',', $login_client->loginToServer($this->tool['SOAP_user'], $this->tool['SOAP_pass']));

                $sakai_content = new \SoapClient($this->tool['SOAP_url'] . '/sakai-ws/soap/contenthosting?wsdl');
                $size_result = $sakai_content->getSiteCollectionSize($session_array[0], $site_id) * 1024;

                $size_result_st = MigrateDAO::formatBytes($size_result);
                $size_limit_st  = MigrateDAO::formatBytes($this->tool['site_size_limit']);
                $size_result_st = "The size of the course content ($size_result_st) exceeds the maximum allowed conversion size ($size_limit_st).";

                $result = $login_client->logout($session_array[0]);
            } catch(Exception $e) {
                // so we can't get login details so no size stuff
            }
        }

        return [
            'site_size' => $size_result,
            'size_result_st' => $size_result_st,
            'site_can_migrate' => $size_result < $this->tool['site_size_limit'],
            'ss' => $this->tool['SOAP_user'] ." ". $this->tool['SOAP_pass']
        ];
    }

    #### CLICKUP
    public function getSiteProperties($site_id) {
        $school = '';
        $department = '';
        $subject = '';
        $result = null;
    
        if ($this->tool['SOAP_active']) {
            try {
                $login_client = new \SoapClient($this->tool['SOAP_url'] . '/sakai-ws/soap/login?wsdl');
                $session_array = explode(',', $login_client->loginToServer($this->tool['SOAP_user'], $this->tool['SOAP_pass']));
    
                $sakai_site = new \SoapClient($this->tool['SOAP_url'] . '/sakai-ws/soap/sakai?wsdl');
    
                if ($sakai_site && isset($session_array[0])) {
                    try {
                        $school = $sakai_site->getSiteProperty($session_array[0], $site_id, 'School');
                        $department = $sakai_site->getSiteProperty($session_array[0], $site_id, 'Department');
                        $subject = $sakai_site->getSiteProperty($session_array[0], $site_id, 'Subject');
        
                        // Handle null responses
                        $school = ($school !== null) ? $school : "No School Data";
                        $department = ($department !== null) ? $department : "No Department Data";
                        $subject = ($subject !== null) ? $subject : "No Subject Data";
        
                        $result = array(
                            'site_id' => $site_id,
                            'school' => $school,
                            'department' => $department,
                            'subject' => $subject
                        );
                    } catch (SoapFault $soapFault) {
                        $result = array(
                            'error' => 'SOAP Error: ' . $soapFault->getMessage()
                        );
                    } catch (Exception $e) {
                        $result = array(
                            'error' => 'General Error: ' . $e->getMessage()
                        );
                    }
                }
                if (isset($session_array[0])) {
                    $login_client->logout($session_array[0]);
                }
            } catch (SoapFault $soapFault) {
                $result = array(
                    'error' => 'SOAP Error: ' . $soapFault->getMessage()
                );
            } catch (Exception $e) {
                $result = array(
                    'error' => 'General Error: ' . $e->getMessage()
                );
            }
        }
        return $result;
    }

    private function makeRequest($method, $url, $data = array()) {
        $headers = array(
            'Authorization: '. $this->tool['api_key'],
            'Content-Type: application/json',
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);    

        if (in_array($method, array('POST', 'PUT'))) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    function getClickupTaskBySiteId($site_id) {
        $vula_site_url = $this->tool['vula_url'].$site_id;
        $task_customFilter = [
            [
                "field_id" => $this->tool['custom_vulaSite_fieldId'],
                "operator" => "=",
                "value" => $vula_site_url
            ]
        ];
                
        $encoded_customFilter = urlencode(json_encode($task_customFilter));
        $endpoint = "{$this->tool['base_url']}team/{$this->tool['workspace_id']}/task?include_closed=true&custom_task_ids=true&team_id={$this->tool['workspace_id']}&custom_fields=$encoded_customFilter";
        
        try {
            $response = $this->makeRequest('GET', $endpoint);
            if (!empty($response['tasks'])) {
            $task = $response['tasks'][0];
            return $task;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $error_message = 'Error occurred while getting clickup task: ' . $e->getMessage();
            return $error_message;
        }
    }

    function getClickupTaskByName($task_name) {
        $endpoint = "{$this->tool['base_url']}list/{$this->tool['list_id']}/task?archived=false&include_closed=true&reverse=true";

        try {
            $response = $this->makeRequest('GET', $endpoint);
            $tasks = $response['tasks'];
            foreach ($tasks as $task) {
                if ($task['name'] === $task_name) {
                    return $task;
                }
            }
        
        } catch (Exception $e) {
            $error_message = 'Error occurred while getting ClickUp task: ' . $e->getMessage();
            return $error_message;
        }
    }

    function getSelfServiceMigrationOptionId($custom_fields) {
        $mig_option = null;
        foreach ($custom_fields as $field) {
            if ($field['id'] === $this->tool['clickup_option_Id']) {
                $options = $field['type_config']['options'];

                foreach ($options as $option) {
                    if ($option['name'] === "Option 3") {
                        $mig_option = $option['orderindex'];
                    }
                }
            }
        }
        return  $mig_option;
    }

    function updateTask($taskId) {
        $clickupOption_fieldId = $this->tool['clickup_option_Id'];
        $endpoint = "{$this->tool['base_url']}task/$taskId/field/$clickupOption_fieldId?custom_task_ids=false";
        $data = ['value' => $this->tool['new_migrationOptionId']];

        $updateResult = [
            'task_id' => $taskId,
            'message' => '',
        ];

        try {
            $response = $this->makeRequest('POST', $endpoint, $data);
            if ($response && isset($response['id']) && $response['id'] === $taskId) {
                $updateResult['message'] = 'ClickUp task updated successfully';
            } else {
                $updateResult['message'] = 'Error updating ClickUp task';
            }
        } catch (Exception $e) {
            $updateResult['message'] = 'An error occurred while updating the ClickUp task: ' . $e->getMessage();
        }
        return $updateResult;
    }
}
