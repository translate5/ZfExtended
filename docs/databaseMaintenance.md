Database Maintenance
====================

Database maintenance will be part of the Install and Update Kit (IuK). If the IuK is ready, this documentation will be superseded by the IuK Documentation!

Current Usage
-------------
Currently the database updater knows two commands:
1. /database/import/
2. /database/catchup/

The first one */database/import/* is the command will print you a list of new SQL files, and provides you a button to import this files into your configured DB.

The second one */database/catchup/* is only needed for the first time using the database updater. Calling this URL will mark all available SQL files as already installed. Regardless if you have applied the SQL contents of the files or not. *Use this command wisely!*

Security
--------
For security reasons the database controller is not accessible by default. 

If you want to use the database updater you have to add temporarily the following ACL to your ACL Rules:

	insert into Zf_acl_rules values(null, 'default', 'noRights', 'database', 'all');

After doing the DB maintenance delete this right again!

	delete from Zf_acl_rules where `module` = 'default' and `role` = 'noRights' and `resource` = 'database' and `right` = 'all';

Alternatively you can add the right to your PM role, if this is OK for your installation. Doing so, it would be more secure to allow only the right *import* instead *all*. So it can not happen, that the user does accidentally overwrite all DB version data with */database/catchup/*.


Configuration: SQL Paths for the automatic DB updater
-----------------------------------------------------
The Install and Update Kit updates the Database Structure automatically. Therefore the search paths of the SQL files have to been configured.
The directories are parsed in the given order. In the future with the Install and Update Kit there is configured only one path in productive systems. This path contains the SQL files of the actual build. This would then be: 

	sqlPaths[] = APPLICATION_PATH "/database/"

For developing (and currently for the productive systems until the IUK is ready) each path with SQL files must be configured:

	sqlPaths[] = APPLICATION_PATH "/../library/ZfExtended/database/"
	sqlPaths[] = APPLICATION_PATH "/modules/default/database/"
	sqlPaths[] = APPLICATION_PATH "/modules/editor/database/"


Configuration: Path to mysql client
-----------------------------------
If your mysql command is not at the default place */usr/bin/mysql* you must configure the path in your installation.ini along with your DB credentials:

	resources.db.executable = /path/to/mysqlclient
