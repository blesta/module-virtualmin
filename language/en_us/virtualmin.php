<?php
/**
 * en_us language for the virtualmin module
 */
// Basics
$lang['Virtualmin.name'] = "VirtualMin";
$lang['Virtualmin.description'] = "Virtualmin is a domain hosting and website control panel, which gives the ability to create and manage many domains and is available in both open source and commercial versions. It is based on Webmin.";
$lang['Virtualmin.module_row'] = "Server";
$lang['Virtualmin.module_row_plural'] = "Servers";
$lang['Virtualmin.module_group'] = "Server Group";
$lang['Virtualmin.tab_stats'] = "Statistics";
$lang['Virtualmin.tab_client_actions'] = "Actions";

// Module management
$lang['Virtualmin.add_module_row'] = "Add Server";
$lang['Virtualmin.add_module_group'] = "Add Server Group";
$lang['Virtualmin.manage.module_rows_title'] = "Servers";
$lang['Virtualmin.manage.module_groups_title'] = "Server Groups";
$lang['Virtualmin.manage.module_rows_heading.name'] = "Server Label";
$lang['Virtualmin.manage.module_rows_heading.hostname'] = "Hostname";
$lang['Virtualmin.manage.module_rows_heading.port'] = "Port";
$lang['Virtualmin.manage.module_rows_heading.accounts'] = "Accounts";
$lang['Virtualmin.manage.module_rows_heading.options'] = "Options";
$lang['Virtualmin.manage.module_groups_heading.name'] = "Group Name";
$lang['Virtualmin.manage.module_groups_heading.servers'] = "Server Count";
$lang['Virtualmin.manage.module_groups_heading.options'] = "Options";
$lang['Virtualmin.manage.module_rows.count'] = "%1\$s / %2\$s"; // %1$s is the current number of accounts, %2$s is the total number of accounts available
$lang['Virtualmin.manage.module_rows.edit'] = "Edit";
$lang['Virtualmin.manage.module_groups.edit'] = "Edit";
$lang['Virtualmin.manage.module_rows.delete'] = "Delete";
$lang['Virtualmin.manage.module_groups.delete'] = "Delete";
$lang['Virtualmin.manage.module_rows.confirm_delete'] = "Are you sure you want to delete this server?";
$lang['Virtualmin.manage.module_groups.confirm_delete'] = "Are you sure you want to delete this server group?";
$lang['Virtualmin.manage.module_rows_no_results'] = "There are no servers.";
$lang['Virtualmin.manage.module_groups_no_results'] = "There are no server groups.";


$lang['Virtualmin.order_options.first'] = "First non-full server";

// Add row
$lang['Virtualmin.add_row.box_title'] = "Add VirtualMin Server";
$lang['Virtualmin.add_row.basic_title'] = "Basic Settings";
$lang['Virtualmin.add_row.add_btn'] = "Add Server";

$lang['Virtualmin.edit_row.box_title'] = "Edit VirtualMin Server";
$lang['Virtualmin.edit_row.basic_title'] = "Basic Settings";
$lang['Virtualmin.edit_row.add_btn'] = "Edit Server";

$lang['Virtualmin.row_meta.server_name'] = "Server Label";
$lang['Virtualmin.row_meta.host_name'] = "Hostname";
$lang['Virtualmin.row_meta.user_name'] = "User Name";
$lang['Virtualmin.row_meta.password'] = "Password";
$lang['Virtualmin.row_meta.port'] = "Port";
$lang['Virtualmin.row_meta.use_ssl'] = "Use SSL when connecting to the API (recommended)";
$lang['Virtualmin.row_meta.account_limit'] = "Account Limit";

// Package fields
$lang['Virtualmin.package_fields.plan'] = "VirtualMin Plan";
$lang['Virtualmin.package_fields.template'] = "VirtualMin Template";

// Service fields
$lang['Virtualmin.service_field.domain'] = "Domain";
$lang['Virtualmin.service_field.username'] = "Username";
$lang['Virtualmin.service_field.password'] = "Password";
$lang['Virtualmin.service_field.confirm_password'] = "Confirm Password";

// Client actions
$lang['Virtualmin.tab_client_actions.change_password'] = "Change Password";
$lang['Virtualmin.tab_client_actions.field_virtualmin_password'] = "Password";
$lang['Virtualmin.tab_client_actions.field_virtualmin_confirm_password'] = "Confirm Password";
$lang['Virtualmin.tab_client_actions.field_password_submit'] = "Update Password";


// Client Service management
$lang['Virtualmin.tab_stats.info_title'] = "Information";
$lang['Virtualmin.tab_stats.info_heading.field'] = "Field";
$lang['Virtualmin.tab_stats.info_heading.value'] = "Value";
$lang['Virtualmin.tab_stats.info.disk_used'] = "Disk Used";
$lang['Virtualmin.tab_stats.info.databases_size'] = "Database Size";
$lang['Virtualmin.tab_stats.info.databases_count'] = "Database Count";
$lang['Virtualmin.tab_stats.info.maximum_databases'] = "Maximum Databases";
$lang['Virtualmin.tab_stats.info.maximum_mailboxes'] = "Maximum Mailboxes";


// Service info
$lang['Virtualmin.service_info.username'] = "Username";
$lang['Virtualmin.service_info.password'] = "Password";
$lang['Virtualmin.service_info.server'] = "Server";
$lang['Virtualmin.service_info.options'] = "Options";
$lang['Virtualmin.service_info.option_login'] = "Log in";


// Tooltips
$lang['Virtualmin.service_field.tooltip.username'] = "You may leave the username blank to automatically generate one.";
$lang['Virtualmin.service_field.tooltip.password'] = "You may leave the password blank to automatically generate one.";


// Errors
$lang['Virtualmin.!error.server_name_valid'] = "You must enter a Server Label.";
$lang['Virtualmin.!error.host_name_valid'] = "The Hostname appears to be invalid.";
$lang['Virtualmin.!error.user_name_valid'] = "The User Name appears to be invalid.";
$lang['Virtualmin.!error.port_valid'] = "The Port appears to be invalid.";
$lang['Virtualmin.!error.password_valid'] = "The Password appears to be invalid.";
$lang['Virtualmin.!error.password_valid_connection'] = "A connection to the server could not be established. Please check to ensure that the Hostname, User Name, Password, and Port are correct.";
$lang['Virtualmin.!error.account_limit_valid'] = "Please enter a valid account limit.";
$lang['Virtualmin.!error.meta[plan].empty'] = "A VirtualMin Plan is required.";
$lang['Virtualmin.!error.meta[template].empty'] = "A VirtualMin Template is required.";
$lang['Virtualmin.!error.api.internal'] = "An internal error occurred, or the server did not respond to the request.";
$lang['Virtualmin.!error.module_row.missing'] = "An internal error occurred. The module row is unavailable.";

$lang['Virtualmin.!error.virtualmin_domain.format'] = "Please enter a valid domain name, e.g. domain.com.";
$lang['Virtualmin.!error.virtualmin_domain.test'] = "Domain name can not start with 'test'.";
$lang['Virtualmin.!error.virtualmin_username.format'] = "The username may contain only letters and numbers and may not start with a number.";
$lang['Virtualmin.!error.virtualmin_username.test'] = "The username may not begin with 'test'.";
$lang['Virtualmin.!error.virtualmin_username.length'] = "The username must be between 1 and 16 characters in length.";
$lang['Virtualmin.!error.virtualmin_password.valid'] = "Password must be at least 8 characters in length.";
$lang['Virtualmin.!error.virtualmin_password.valid'] = "Password must be at least 8 characters in length.";
?>