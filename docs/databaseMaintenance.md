Database Maintenance
====================

Database maintenance will be part of the Install and Update Kit (IuK). If the IuK is ready, this documentation will be superseded by the IuK Documentation!

Current Usage
-------------
Currently the database updater is accessible under:

    /database/import/

On */database/import/* you will get a neat GUI, where you get a list of new or modified SQL files. PHP Files are also listed, since sometimes a PHP Script is needed to do complex data conversions. 

The listed files can be individually selected by checkboxes, and then be processed on different ways: 
The default way is to import SQL files into your configured DB. Selected PHP files are included, to be processed.

An additional checkbox enables the "catchup mode". Catchup Mode means, that the selected files are marked as "imported", but the contents are not aplied to the DB. This is needed for the first time using the database updater, or if you have manually applied a SQL / PHP file, and you have to mark it as "done".
Using the "catchup mode" will mark all selected SQL/PHP files as already installed! Regardless if you have applied the contents of the files or not! *Use this mode wisely!*

PHP Files
---------
Sometimes it becomes necessary to use a PHP script for data conversion, not only a plain SQL script. In this case, a PHP file can be created instead a SQL file. For PHP files the same search paths are used, as for SQL files. See below on SQL Path configuration.

The PHP files are included in the DBUpdater Scope. That means you have access to the ZfExtended Framework - with the scope from within the calling method. That means you can either use Entity or DB Models, with preconfigured DB credentials. Then the script can not be called from console! If you plan to use your script also in console, you have to implement the following Syntax:

    /usr/bin/php 123-DB-MANIPULATION-SCRIPT.php DBHOST DBNAME DBUSER DBPASSWD [DBPORT [DBSOCKET]]
    
This should be implemented with the $argv array, since the DBUpdater fills the $argv array with the above parameters for compatibility.

*Avoid "die" or "exit" calls in the PHP script, since this would break the whole DbUpdater process!* 
Exceptions can and should be used instead!

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


For Developers: How and where to place SQL files
------------------------------------------------
Each project, library and module can have its own *database* directory, called a "SQL package" in this docu. In the directory are located the SQL files. Each database directory contains a file *metainformation.xml*. This file contains additional informations to the SQL package. In general this is the *name* of the SQL package of this module / library. This is needed by the database updater to identify the source of the SQL files, the updater stores this SQL package name in the dbversion table.

In addition one can place dependency informations to SQL files. So you can assure that a specific SQL file was installed before another. By using package names, the dependencies can also come across from different packages.

**The dependency resolution is currently not implemented, its somekind of a working draft.**

Content of a metainformation.xml:

	<database>
		<name>sqlPackageName</name>
		<file>
			<name>thisfilehasdependencies.sql</name>
			<dependencies>
				<dependency>thismustbebefore.sql</dependency>
				<dependency package="anotherOne">thistoo.sql</dependency>
			</dependencies>
		</file>
	</database>

### Simple Overwrite Mechanism
The Database Updater knows a simple way to overwrite specific SQL files. If in one DB package a directory exists with the same name as one of the other packages, the files of this directory supersede the equal named files in target package. 

Example: 

	APPLICATION_PATH "/modules/default/database/editor/123-foo.sql"
	APPLICATION_PATH "/modules/editor/database/123-foo.sql"

Both files has the same name. The second SQL package has the configured name *editor*. In this case the DB Updater recognizes, that it should use the first file instead the second one.