<?php
namespace Migration\DAO;

class MigrateDAO {

    private $PDOX;
    private $p;

    public function __construct($PDOX, $p) {
        $this->PDOX = $PDOX;
        $this->p = $p;
    }

    function getMigration($link_id, $user_id, $site_id, $provider, $is_admin) {

        $query = "SELECT 
            `site`.notification as `notification`,
            `migration`.created_at, `site`.started_at, `site`.modified_at,
            `user`.displayname, `user`.email,
            `site`.state, `site`.`active`, `site`.workflow, `migration`.is_admin
            FROM {$this->p}migration `migration`
            left join {$this->p}migration_site `site` on `site`.link_id = `migration`.link_id
            left join {$this->p}lti_user `user` on `user`.user_id = `site`.started_by
            WHERE `migration`.link_id = :linkId and `site`.state is not null limit 1;";

        if ($is_admin) {
            $query = "SELECT `migration`.created_at, `migration`.is_admin, `site`.state, '' as `email`, '' as `displayname`, '' as `notification`
                FROM {$this->p}migration `migration`
                left join {$this->p}migration_site `site` on `site`.link_id = `migration`.link_id
                WHERE `migration`.link_id = :linkId limit 1;";
        }

        $arr = array(':linkId' => $link_id);
        $rows = $this->PDOX->rowDie($query, $arr);

        if (gettype($rows) == "boolean") {
            if ($this->createEmpty($link_id, $user_id, $site_id, $provider, $is_admin)) {
                return $this->getMigration($link_id, $user_id, $site_id, $provider, $is_admin);
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
                VALUES (:linkId, :siteid, NOW(), :userId, :provider, :state)", 
            array(':linkId' => $link_id, ':siteid' => $site_id, ':userId' => $user_id, ':provider' => $is_admin ? b'1' : b'0', 
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

        $query = "SELECT `site`.site_id, `site`.title, `site`.state 
            FROM {$this->p}migration_site `site`
            where `site`.link_id = :linkId
            having `site`.state <> 'admin';";

        $arr = array(':linkId' => $link_id);
        return $this->PDOX->allRowsDie($query, $arr);
    }

    function startMigration($link_id, $user_id, $site_id, $notifications) {
        $now = date("Y-m-d H:i:s");
        $workflow = ["$now,000 - [INFO] - Started Migration ($site_id)","$now,001 - [INFO] - Scheduled Export..."];

        $query = "UPDATE {$this->p}migration_site
                SET modified_at = NOW(), modified_by = :userId, started_at = NOW(), started_by = :userId, 
                    workflow = :workflow, active = 1, state='starting', notification = :notifications
                 WHERE link_id = :linkId;";

        $arr = array(':linkId' => $link_id, ':userId' => $user_id, ':notifications' => $notifications, ':workflow' => json_encode($workflow));
        return $this->PDOX->queryDie($query, $arr);
    }

    function updateMigration($link_id, $user_id, $notifications) {
        $query = "UPDATE {$this->p}migration_site
                SET modified_at = NOW(), modified_by = :userId, notification = :notifications
                 WHERE link_id = :linkId;";
        $arr = array(':linkId' => $link_id, ':userId' => $user_id, ':notifications' => $notifications);
        return $this->PDOX->queryDie($query, $arr);
    }

    function addSitesMigration($link_id, $user_id, $sites) {
        $result = [];
        foreach ($sites as $site) {

            if (strlen($site) > 3) {
                $now = date("Y-m-d H:i:s");
                $workflow = ["$now,000 - [INFO] - Started Migration ($site)","$now,001 - [INFO] - Scheduled Export..."];

                $query = "REPLACE INTO {$this->p}migration_site 
                (site_id, link_id, started_at, started_by, modified_at, modified_by, active, state, workflow) 
                    VALUES (:siteId, :linkId, NOW(), :userId, NOW(), :userId, 1, 'starting', :workflow);";
                
                $arr = array(':siteId' => $site, ':linkId' => $link_id, ':userId' => $user_id, ':workflow' => json_encode($workflow));

                try {
                    $this->PDOX->queryDie($query, $arr);
                    array_push($result, 1);
                } catch (PDOException $e) {
                    array_push($result, 0);
                }
            }
        }

        return $result;
    }
    
    function getWorkflow($link_id, $site_id) {
        $query = "SELECT workflow FROM {$this->p}migration_site where link_id = :linkId and site_id = :siteId;";
        $rows = $this->PDOX->rowDie($query, array(':siteId' => $site_id, ':linkId' => $link_id));

        return ($rows == 0 ? [] : $rows);
    }
    
}