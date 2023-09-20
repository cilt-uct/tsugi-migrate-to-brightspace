<?php
// Configuration file - copy from tool-config_dist.php to tool-config.php
// and then edit.

if ((basename(__FILE__, '.php') != 'tool-config') && (file_exists('tool-config.php'))) {
    include 'tool-config.php';
    return;
}

if ((basename(__FILE__, '.php') != 'tool-config') && (file_exists('../tool-config.php'))) {
    include '../tool-config.php';
    return;
}

# The configuration file - stores the paths to the scripts
$tool = array();
$tool['debug'] = FALSE;
$tool['active'] = TRUE; # if false will show coming soon page

$tool['brightspace_url'] = 'https://amathuba.uct.ac.za/d2l/home/';
$tool['brightspace_log_url'] = 'https://amathuba.uct.ac.za/d2l/le/conversion/import/';
$tool['vula_url'] = 'https://vula.uct.ac.za/portal/site/';
$tool['jira_url'] = 'https://jira.cilt.uct.ac.za/issues/?jql=project%3D%22MIG%22%20and%20%22Site%20ID%22%20%20~%20%22';

$tool['site_size_limit'] = 104857600;   # 100MB
$tool['site_size_limit'] = 1073741824;  # 1GB
$tool['site_size_limit'] = 32212254720; # 30GB
$tool['SOAP_active'] = TRUE;
$tool['SOAP_url'] = 'https://vula.uct.ac.za';
$tool['SOAP_user'] = 'username';
$tool['SOAP_pass'] = 'password';

# clickup config
$tool['api_key'] = 'apikey';
$tool['list_id'] = 'listid';
$tool['workspace_id'] = 'workspaceid';
$tool['base_url'] = 'https://api.clickup.com/api/v2/';
$tool['clickup_option_Id'] = 'clickupOptionId'; // clickup custom field id for migration options
$tool['new_migrationOptionId'] = 'newMigrationOptionId'; // clickup uuid of option 3
$tool['custom_vulaSite_fieldId'] = 'customVulaSiteFieldId'; // clickup uuid for custom vula site field
$tool['vuladev_url'] = 'https://vuladev.uct.ac.za/portal/site/';

# these sites are used for development - so ignore coming soon page
$tool['dev'] = [];

$departments = [
    ['other','University - wide Community or Activity'],
    ['COM','Faculty of Commerce'],
    ['EBE','Faculty of Engineering & Built Environment'],
    ['FHS','Faculty of Health Sciences'],
    ['HUM','Faculty of Humanities'],
    ['LAW','Faculty of Law'],
    ['SCI','Faculty of Science'],
    ['GSB','Graduate School of Business (GSB)'],
    ['CHED','Centre for Higher Education Development']
];

$full_departments_list = [
    ['ACC','COM','College of Accounting'],
    ['DOC','COM','Dean\'s Office: Commerce'],
    ['FTX','COM','Dept. of Finance & Tax'],
    ['INF','COM','Dept. of Information Systems'],
    ['ECO','COM','School of Economics'],
    ['BUS','COM','School of Management Studies'],

    ['APG','EBE','APG: School of Architec, Planning & Geomatic'],
    ['CON','EBE','CEM: Dept. of Construction Econ & Managemnt'],
    ['CHE','EBE','CHE: Dept. of Chemical Engineering'],
    ['CIV','EBE','CIV: Dept. of Civil Engineering'],
    ['CPD','EBE','EBE: Contin. Professional Developmnt Unit'],
    ['EEE','EBE','EEE: Dept. of Electrical Engineering'],
    ['EMU','EBE','EMU: Electron Microscope Unit'],
    ['ERI','EBE','Energy Research Centre'],
    ['MEC','EBE','MEC: Dept. of Mechanical Engineering'],
    ['END','EBE','Professional Communication Studies'],

    ['AAE','FHS','ANAES: Dept. of Anaesthesia'],
    ['MED','FHS','Faculty of Health Sciences'],
    ['DOM','FHS','FHS: Dean\'s Office: Health Sciences'],
    ['AHS','FHS','HRS: Dept. of Health & Rehab Sciences'],
    ['HSE','FHS','HSE: Dept. of Health Sciences Education'],
    ['HUB','FHS','HUB: Dept. of Human Biology'],
    ['IBS','FHS','IBMS: Dept. of Integrtve Biomed Sciences'],
    ['MDN','FHS','MED: Dept. of Medicine'],
    ['OBS','FHS','OBG: Dept. of Obstetrics & Gynaecology'],
    ['PED','FHS','PAED: Children\'s Institute of UCT'],
    ['PTY','FHS','PATH: Dept. of Pathology'],
    ['PPH','FHS','PHFM: Dept. of Public Health & Fam Med.'],
    ['PRY','FHS','PRY: Dept. of Psychiatry & Mental Health'],
    ['RAY','FHS','RAD: Dept. of Radiation Medicine'],
    ['CHM','FHS','SUR: Dept. of Surgery'],

    ['AGI','HUM','African Gender Institute'],
    ['ALL','HUM','African Languages & Literature'],
    ['CAS','HUM','African Studies'],
    ['SAN','HUM','Anthropology (ANS)'],
    ['FAM','HUM','Centre for Film & Media Studies'],
    ['CLA','HUM','Classical Studies'],
    ['MUZ','HUM','College of Music'],
    ['REL','HUM','Dept. for the Study of Religions'],
    ['DRM','HUM','Dept. of Drama'],
    ['ELL','HUM','Dept. of English Language & Literature'],
    ['HST','HUM','Dept. of Historical Studies'],
    ['LIS','HUM','Dept. of Knowledge & Info Stewardship'],
    ['PHI','HUM','Dept. of Philosophy'],
    ['POL','HUM','Dept. of Political Studies'],
    ['PSY','HUM','Dept. of Psychology'],
    ['SWK','HUM','Dept. of Social Development'],
    ['SOC','HUM','Dept. of Sociology'],
    ['HEB','HUM','Hebrew Language & Literature'],
    ['FIN','HUM','Michaelis School of Fine Art'],
    ['AXL','HUM','School of African&GenderStuds, Anth&Ling'],
    ['EDN','HUM','School of Education'],
    ['SLL','HUM','School of Languages & Literatures'],
    ['TDP','HUM','Theatre,Dance&Performance Studies(CTDPS)'],

    ['DOL','LAW','Dean\'s Office: Law'],
    ['CML','LAW','Dept. of Commercial Law'],
    ['PVL','LAW','Dept. of Private Law'],
    ['RDL','LAW','Dept. of Private Law'],
    ['PBL','LAW','Dept. of Public Law'],

    ['AGE','SCI','AGE: Dept. of Archaeology'],
    ['AST','SCI','AST: Dept. of Astronomy'],
    ['BIO','SCI','BIO: Dept. of Biological Sciences'],
    ['CEM','SCI','CEM: Dept. of Chemistry'],
    ['CSC','SCI','CSC: Dept. of Computer Science'],
    ['DOH','SCI','Dean\'s Office: Humanities'],
    ['EGS','SCI','EGS:Dept of Environ & Geographic Science'],
    ['DSC','SCI','FSC: Dean\'s Office: Science'],
    ['GEO','SCI','GEO: Dept. of Geological Sciences'],
    ['MAM','SCI','MAM: Dept of Mathematics & Applied Maths'],
    ['MCB','SCI','MCB: Dept. of Molecular & Cell Biology'],
    ['PHY','SCI','PHY: Dept. of Physics'],
    ['SEA','SCI','SEA: Dept. of Oceanography'],
    ['STA','SCI','STA: Dept. of Statistical Sciences'],

    ['GSB','GSB','Graduate School of Business (GSB)'],
    ['GPP','GSB','The Nelson Mandela School of Public Gov'],
];