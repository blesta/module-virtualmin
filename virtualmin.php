<?php
use Blesta\Core\Util\Validate\Server;

/**
 * Virtualmin Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.virtualmin
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Virtualmin extends Module
{
    /**
     * Initializes the module
     */
    public function __construct()
    {
        // Load configuration required by this module
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this module
        Loader::loadComponents($this, ['Input', 'Net']);
        $this->Http = $this->Net->create('Http');

        // Load the language required by this module
        Language::loadLang('virtualmin', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Returns all tabs to display to an admin when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title.
     *  Example: ['methodName' => 'Title', 'methodName2' => 'Title2']
     */
    public function getAdminTabs($package)
    {
        return [
            'tabStats' => Language::_('Virtualmin.tab_stats', true)
        ];
    }

    /**
     * Returns all tabs to display to a client when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title.
     *  Example: ['methodName' => 'Title', 'methodName2' => 'Title2']
     */
    public function getClientTabs($package)
    {
        return [
            'tabClientActions' => Language::_('Virtualmin.tab_client_actions', true),
            'tabClientStats' => Language::_('Virtualmin.tab_stats', true)
        ];
    }

    /**
     * Returns an array of available service deligation order methods. The module
     * will determine how each method is defined. For example, the method 'first'
     * may be implemented such that it returns the module row with the least number
     * of services assigned to it.
     *
     * @return array An array of order methods in key/value paris where the key is the type to be stored for the
     *  group and value is the name for that option
     * @see Module::selectModuleRow()
     */
    public function getGroupOrderOptions()
    {
        return ['first' => Language::_('Virtualmin.order_options.first', true)];
    }

    /**
     * Determines which module row should be attempted when a service is provisioned
     * for the given group based upon the order method set for that group.
     *
     * @return int The module row ID to attempt to add the service with
     * @see Module::getGroupOrderOptions()
     */
    public function selectModuleRow($module_group_id)
    {
        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        $group = $this->ModuleManager->getGroup($module_group_id);

        if ($group) {
            switch ($group->add_order) {
                default:
                case 'first':
                    foreach ($group->rows as $row) {
                        return $row->id;
                    }

                    break;
            }
        }

        return 0;
    }

    /**
     * Returns all fields used when adding/editing a package, including any
     * javascript to execute when the page is rendered with these fields.
     *
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render as well as any
     *  additional HTML markup to include
     */
    public function getPackageFields($vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Fetch all packages available for the given server or server group
        $module_row = null;
        if (isset($vars->module_group) && $vars->module_group == '') {
            if (isset($vars->module_row) && $vars->module_row > 0) {
                $module_row = $this->getModuleRow($vars->module_row);
            } else {
                $rows = $this->getModuleRows();
                if (isset($rows[0])) {
                    $module_row = $rows[0];
                }

                unset($rows);
            }
        } else {
            // Fetch the 1st server from the list of servers in the selected group
            $rows = $this->getModuleRows($vars->module_group);

            if (isset($rows[0])) {
                $module_row = $rows[0];
            }

            unset($rows);
        }

        $templates = [];
        $plans = [];

        if ($module_row) {
            $plans = $this->getPlans($module_row);
            $templates = $this->getTemplates($module_row);
        }

        // Set the VirtualMin package as a selectable option
        $plan = $fields->label(Language::_('Virtualmin.package_fields.plan', true), 'virtualmin_plan');
        $plan->attach(
            $fields->fieldSelect(
                'meta[plan]',
                $plans,
                (isset($vars->meta['plan']) ? $vars->meta['plan'] : null),
                ['id' => 'virtualmin_plan']
            )
        );
        $fields->setField($plan);

        // Set the VirtualMin package as a selectable option
        $template = $fields->label(Language::_('Virtualmin.package_fields.template', true), 'virtualmin_template');
        $template->attach(
            $fields->fieldSelect(
                'meta[template]',
                $templates,
                (isset($vars->meta['template']) ? $vars->meta['template'] : null),
                ['id' => 'virtualmin_template']
            )
        );
        $fields->setField($template);

        return $fields;
    }

    /**
     * Validates input data when attempting to add a package, returns the meta
     * data to save when adding a package. Performs any action required to add
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being added.
     *
     * @param array An array of key/value pairs used to add the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addPackage(array $vars = null)
    {
        // Set rules to validate input data
        $this->Input->setRules($this->getPackageRules($vars));

        // Build meta data to return
        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }
        return $meta;
    }

    /**
     * Validates input data when attempting to edit a package, returns the meta
     * data to save when editing a package. Performs any action required to edit
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being edited.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array An array of key/value pairs used to edit the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editPackage($package, array $vars = null)
    {
        // Set rules to validate input data
        $this->Input->setRules($this->getPackageRules($vars));

        // Build meta data to return
        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }
        return $meta;
    }

    /**
     * Returns the rendered view of the manage module page
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manager module
     *  page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the manager module page
     */
    public function manageModule($module, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('manage', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('module', $module);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page
     *
     * @param array $vars An array of post data submitted to or on the add module row
     *  page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the add module row page
     */
    public function manageAddRow(array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('add_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        // Set unspecified checkboxes
        if (!empty($vars)) {
            if (empty($vars['use_ssl'])) {
                $vars['use_ssl'] = 'false';
            }
        }

        $this->view->set('vars', (object) $vars);
        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the edit module row
     *  page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the edit module row page
     */
    public function manageEditRow($module_row, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('edit_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
        } else {
            // Set unspecified checkboxes
            if (empty($vars['use_ssl'])) {
                $vars['use_ssl'] = 'false';
            }
        }

        $this->view->set('vars', (object) $vars);
        return $this->view->fetch();
    }

    /**
     * Adds the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being added. Returns a set of data, which may be
     * a subset of $vars, that is stored for this module row
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function addModuleRow(array &$vars)
    {
        $meta_fields = ['server_name', 'host_name', 'user_name', 'port', 'use_ssl', 'password', 'account_limit'];
        $encrypted_fields = ['user_name', 'port', 'password'];

        // Set unspecified checkboxes
        if (empty($vars['use_ssl'])) {
            $vars['use_ssl'] = 'false';
        }

        $this->Input->setRules($this->getRowRules($vars));

        // Validate module row
        if ($this->Input->validates($vars)) {
            $vars['host_name'] = strtolower($vars['host_name']);

            // Build the meta data for this row
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }

            return $meta;
        }
    }

    /**
     * Edits the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being updated. Returns a set of data, which may be
     * a subset of $vars, that is stored for this module row
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of module info to update
     * @return array A numerically indexed array of meta fields for the module row containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function editModuleRow($module_row, array &$vars)
    {
        $meta_fields = ['server_name', 'host_name', 'user_name', 'port', 'use_ssl', 'password', 'account_limit'];
        $encrypted_fields = ['user_name', 'port', 'password'];

        // Set unspecified checkboxes
        if (empty($vars['use_ssl'])) {
            $vars['use_ssl'] = 'false';
        }

        $this->Input->setRules($this->getRowRules($vars));

        // Validate module row
        if ($this->Input->validates($vars)) {
            $vars['host_name'] = strtolower($vars['host_name']);

            // Build the meta data for this row
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }

            return $meta;
        }
    }

    /**
     * Deletes the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being deleted.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     */
    public function deleteModuleRow($module_row)
    {

    }

    /**
     * Returns all fields to display to an admin attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as well
     *  as any additional HTML markup to include
     */
    public function getAdminAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Create domain label
        $domain = $fields->label(Language::_('Virtualmin.service_field.domain', true), 'virtualmin_domain');
        // Create domain field and attach to domain label
        $domain->attach(
            $fields->fieldText(
                'virtualmin_domain',
                (isset($vars->virtualmin_domain) ? $vars->virtualmin_domain : null),
                ['id' => 'virtualmin_domain']
            )
        );
        // Set the label as a field
        $fields->setField($domain);

        // Create username label
        $username = $fields->label(Language::_('Virtualmin.service_field.username', true), 'virtualmin_username');
        // Create username field and attach to username label
        $username->attach(
            $fields->fieldText(
                'virtualmin_username',
                (isset($vars->virtualmin_username) ? $vars->virtualmin_username : null),
                ['id' => 'virtualmin_username']
            )
        );
        // Add tooltip
        $tooltip = $fields->tooltip(Language::_('Virtualmin.service_field.tooltip.username', true));
        $username->attach($tooltip);
        // Set the label as a field
        $fields->setField($username);

        // Create password label
        $password = $fields->label(Language::_('Virtualmin.service_field.password', true), 'virtualmin_password');
        // Create password field and attach to password label
        $password->attach(
            $fields->fieldPassword(
                'virtualmin_password',
                ['id' => 'virtualmin_password', 'value' => (isset($vars->virtualmin_password) ? $vars->virtualmin_password : null)]
            )
        );
        // Add tooltip
        $tooltip = $fields->tooltip(Language::_('Virtualmin.service_field.tooltip.password', true));
        $password->attach($tooltip);
        // Set the label as a field
        $fields->setField($password);

        // Confirm password label
        $confirm_password = $fields->label(
            Language::_('Virtualmin.service_field.confirm_password', true),
            'virtualmin_confirm_password'
        );
        // Create confirm password field and attach to password label
        $confirm_password->attach(
            $fields->fieldPassword(
                'virtualmin_confirm_password',
                ['id' => 'virtualmin_confirm_password', 'value' => (isset($vars->virtualmin_password) ? $vars->virtualmin_password : null)]
            )
        );
        // Add tooltip
        $tooltip = $fields->tooltip(Language::_('Virtualmin.service_field.tooltip.password', true));
        $confirm_password->attach($tooltip);
        // Set the label as a field
        $fields->setField($confirm_password);

        return $fields;
    }

    /**
     * Returns all fields to display to a client attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as well as any
     *  additional HTML markup to include
     */
    public function getClientAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Create domain label
        $domain = $fields->label(Language::_('Virtualmin.service_field.domain', true), 'virtualmin_domain');
        // Create domain field and attach to domain label
        $domain->attach(
            $fields->fieldText(
                'virtualmin_domain',
                (isset($vars->virtualmin_domain) ? $vars->virtualmin_domain : ($vars->domain ?? null)),
                ['id' => 'virtualmin_domain']
            )
        );
        // Set the label as a field
        $fields->setField($domain);

        return $fields;
    }

    /**
     * Returns all fields to display to an admin attempting to edit a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as well as any
     *  additional HTML markup to include
     */
    public function getAdminEditFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        // Create domain label
        $domain = $fields->label(Language::_('Virtualmin.service_field.domain', true), 'virtualmin_domain');
        // Create domain field and attach to domain label
        $domain->attach(
            $fields->fieldText(
                'virtualmin_domain',
                (isset($vars->virtualmin_domain) ? $vars->virtualmin_domain : null),
                ['id' => 'virtualmin_domain']
            )
        );
        // Set the label as a field
        $fields->setField($domain);

        // Create username label
        $username = $fields->label(Language::_('Virtualmin.service_field.username', true), 'virtualmin_username');
        // Create username field and attach to username label
        $username->attach(
            $fields->fieldText(
                'virtualmin_username',
                (isset($vars->virtualmin_username) ? $vars->virtualmin_username : null),
                ['id' => 'virtualmin_username']
            )
        );
        // Set the label as a field
        $fields->setField($username);

        // Create password label
        $password = $fields->label(Language::_('Virtualmin.service_field.password', true), 'virtualmin_password');
        // Create password field and attach to password label
        $password->attach(
            $fields->fieldPassword(
                'virtualmin_password',
                ['id' => 'virtualmin_password', 'value' => (isset($vars->virtualmin_password) ? $vars->virtualmin_password : null)]
            )
        );
        // Set the label as a field
        $fields->setField($password);

        return $fields;
    }

    /**
     * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @return boolean True if the service validates, false otherwise. Sets Input errors when false.
     */
    public function validateService($package, array $vars = null)
    {
        $this->Input->setRules($this->getServiceRules($vars));
        return $this->Input->validates($vars);
    }

    /**
     * Attempts to validate an existing service against a set of service info updates. Sets Input errors on failure.
     *
     * @param stdClass $service A stdClass object representing the service to validate for editing
     * @param array $vars An array of user-supplied info to satisfy the request
     * @return bool True if the service update validates or false otherwise. Sets Input errors when false.
     */
    public function validateServiceEdit($service, array $vars = null)
    {
        $this->Input->setRules($this->getServiceRules($vars, true));
        return $this->Input->validates($vars);
    }

    /**
     * Returns the rule set for adding/editing a service
     *
     * @param array $vars A list of input vars
     * @param bool $edit True to get the edit rules, false for the add rules
     * @return array Service rules
     */
    private function getServiceRules(array $vars = null, $edit = false)
    {
        $rules = [
            'virtualmin_domain' => [
                'format' => [
                    'rule' => [[$this, 'validateHostName']],
                    'message' => Language::_('Virtualmin.!error.virtualmin_domain.format', true)
                ],
                'test' => [
                    'rule' => ['substr_compare', 'test', 0, 4, true],
                    'message' => Language::_('Virtualmin.!error.virtualmin_domain.test', true)
                ]
            ],
            'virtualmin_username' => [
                'format' => [
                    'if_set' => true,
                    'rule' => ['matches', '/^[a-z]([a-z0-9])*$/i'],
                    'message' => Language::_('Virtualmin.!error.virtualmin_username.format', true)
                ],
                'test' => [
                    'if_set' => true,
                    'rule' => ['matches', '/^(?!test)/'],
                    'message' => Language::_('Virtualmin.!error.virtualmin_username.test', true)
                ],
                'length' => [
                    'if_set' => true,
                    'rule' => ['betweenLength', 1, 16],
                    'message' => Language::_('Virtualmin.!error.virtualmin_username.length', true)
                ]
            ],
            'virtualmin_password' => [
                'valid' => [
                    'if_set' => true,
                    'rule' => ['isPassword', 8],
                    'message' => Language::_('Virtualmin.!error.virtualmin_password.valid', true),
                    'last' => true
                ],
            ],
            'virtualmin_confirm_password' => [
                'matches' => [
                    'if_set' => true,
                    'rule' => ['compares', '==', (isset($vars['virtualmin_password'])
                            ? $vars['virtualmin_password']
                            : '')],
                    'message' => Language::_('Virtualmin.!error.virtualmin_password.matches', true)
                ]
            ]
        ];

        if (!isset($vars['virtualmin_domain']) || strlen($vars['virtualmin_domain']) < 4) {
            unset($rules['virtualmin_domain']['test']);
        }

        // Set the values that may be empty
        $empty_values = ['virtualmin_username', 'virtualmin_password'];

        if ($edit) {
            // If this is an edit and no password given then don't evaluate password
            // since it won't be updated
            if (!array_key_exists('virtualmin_password', $vars) || $vars['virtualmin_password'] == '') {
                unset($rules['virtualmin_password']);
            }

            // Validate domain if given
            $rules['virtualmin_domain']['format']['if_set'] = true;
            $rules['virtualmin_domain']['test']['if_set'] = true;
        }

        // Remove rules on empty fields
        foreach ($empty_values as $value) {
            if (empty($vars[$value])) {
                unset($rules[$value]);
            }
        }

        return $rules;
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure,
     * preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being added (if the current service is an addon service service and parent service
     *  has already been provisioned)
     * @param string $status The status of the service being added. These include:
     *    - active
     *    - canceled
     *    - pending
     *    - suspended
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        $row = $this->getModuleRow();

        if (!$row) {
            $this->Input->setErrors(
                ['module_row' => ['missing' => Language::_('Virtualmin.!error.module_row.missing', true)]]
            );

            return;
        }

        // Generate username/password
        if (array_key_exists('virtualmin_domain', $vars)) {
            Loader::loadModels($this, ['Clients']);

            if (empty($vars['virtualmin_username'])) {
                $vars['virtualmin_username'] = $this->generateUsername($vars['virtualmin_domain']);
            }

            // Generate a password
            if (empty($vars['virtualmin_password'])) {
                $vars['virtualmin_password'] = $this->generatePassword();
                $vars['virtualmin_confirm_password'] = $vars['virtualmin_password'];
            }

            // Use client's email address
            if (isset($vars['client_id']) && ($client = $this->Clients->get($vars['client_id'], false))) {
                $vars['virtualmin_email'] = $client->email;
            }
        }

        $params = $this->getFieldsFromInput((array) $vars, $package);

        $this->validateService($package, $vars);

        if ($this->Input->errors()) {
            return;
        }

        // Only provision the service if 'use_module' is true
        if ($vars['use_module'] == 'true') {
            $masked_params = $params;
            $masked_params['password'] = '***';
            $this->log($row->meta->host_name . '|create-domain', serialize($masked_params), 'input', true);
            unset($masked_params);

            $response = $this->apiCall(
                $row->meta->host_name,
                $row->meta->user_name,
                $row->meta->port,
                $row->meta->password,
                $row->meta->use_ssl,
                'create-domain',
                $params
            );

            if (isset($response->status) && isset($response->error) && $response->status !== 'success') {
                $this->log($row->meta->host_name . '|create-domain', serialize($response), 'output');
                $this->Input->setErrors(array(array($response->error)));
            }

            if ($this->Input->errors()) {
                return;
            }

            $this->log($row->meta->host_name . '|create-domain', serialize($response), 'output', true);
        }

        // Return service fields
        return [
            [
                'key' => 'virtualmin_domain',
                'value' => $params['domain'],
                'encrypted' => 0
            ],
            [
                'key' => 'virtualmin_username',
                'value' => $params['user'],
                'encrypted' => 0
            ],
            [
                'key' => 'virtualmin_password',
                'value' => $params['pass'],
                'encrypted' => 1
            ],
            [
                'key' => 'virtualmin_confirm_password',
                'value' => $params['pass'],
                'encrypted' => 1
            ]
        ];
    }

    /**
     * Edits the service on the remote server. Sets Input errors on failure,
     * preventing the service from being edited.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being edited (if the current service is an addon service)
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editService($package, $service, array $vars = null, $parent_package = null, $parent_service = null)
    {
        $row = $this->getModuleRow();
        $params = [];

        $this->validateServiceEdit($package, $vars);

        if ($this->Input->errors()) {
            return;
        }

        $service_fields = $this->serviceFieldsToObject($service->fields);

        // Remove password if not being updated
        if (isset($vars['virtualmin_password']) && $vars['virtualmin_password'] == '') {
            unset($vars['virtualmin_password']);
        }

        // Only update the service if 'use_module' is true
        if ($vars['use_module'] == 'true') {
            // Check for fields that changed
            $delta = [];
            foreach ($vars as $key => $value) {
                if (!array_key_exists($key, $service_fields) || $vars[$key] != $service_fields->$key) {
                    $delta[$key] = $value;
                }
            }
            $params['domain'] = $service_fields->virtualmin_domain;

            // Update domain (if changed)
            if (isset($delta['virtualmin_domain'])) {
                $params['newdomain'] = $delta['virtualmin_domain'];
            }

            // Update password (if changed)
            if (isset($delta['virtualmin_password'])) {
                $params['pass'] = $delta['virtualmin_password'];
            }

            // Update username (if changed), do last so we can always rely on
            //  $service_fields['virtualmin_username'] to contain the username
            if (isset($delta['virtualmin_username'])) {
                $params['user'] = $delta['virtualmin_username'];
            }

            $response = $this->apiCall(
                $row->meta->host_name,
                $row->meta->user_name,
                $row->meta->port,
                $row->meta->password,
                $row->meta->use_ssl,
                'modify-domain',
                $params
            );

            if (isset($response->status) && isset($response->error) && $response->status !== 'success') {
                $this->log($row->meta->host_name . '|modify-domain', serialize($response), 'output');
                $this->Input->setErrors(array(array($response->error)));
            } else {
                $this->log($row->meta->host_name . '|modify-domain', serialize($response), 'output', true);
            }
        }

        // Set fields to update locally
        $fields = ['virtualmin_domain', 'virtualmin_username', 'virtualmin_password'];
        foreach ($fields as $field) {
            if (property_exists($service_fields, $field) && isset($vars[$field])) {
                $service_fields->{$field} = $vars[$field];
            }
        }

        // Set the confirm password to the password
        $service_fields->virtualmin_confirm_password = $service_fields->virtualmin_password;

        // Return all the service fields
        $fields = [];
        $encrypted_fields = ['virtualmin_password', 'virtualmin_confirm_password'];
        foreach ($service_fields as $key => $value) {
            $fields[] = [
                'key' => $key,
                'value' => $value,
                'encrypted' => (in_array($key, $encrypted_fields) ? 1 : 0)
            ];
        }

        return $fields;
    }

    /**
     * Suspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being suspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being suspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array
     *  of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $response = $this->apiCall(
                $row->meta->host_name,
                $row->meta->user_name,
                $row->meta->port,
                $row->meta->password,
                $row->meta->use_ssl,
                'disable-domain',
                [
                    'domain' => $service_fields->virtualmin_domain,
                ]
            );
            if (isset($response->status) && isset($response->error) && $response->status !== 'success') {
                $this->log($row->meta->host_name . '|disable-domain', serialize($response), 'output');
                $this->Input->setErrors(array(array($response->error)));
            } else {
                $this->log($row->meta->host_name . '|disable-domain', serialize($response), 'output', true);
            }
        }
        return null;
    }

    /**
     * Unsuspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being unsuspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being unsuspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array
     *  of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $response = $this->apiCall(
                $row->meta->host_name,
                $row->meta->user_name,
                $row->meta->port,
                $row->meta->password,
                $row->meta->use_ssl,
                'enable-domain',
                [
                    'domain' => $service_fields->virtualmin_domain,
                ]
            );

            if (isset($response->status) && isset($response->error) && $response->status !== 'success') {
                $this->log($row->meta->host_name . '|enable-domain', serialize($response), 'output');
                $this->Input->setErrors(array(array($response->error)));
            } else {
                $this->log($row->meta->host_name . '|enable-domain', serialize($response), 'output', true);
            }
        }
        return null;
    }

    /**
     * Cancels the service on the remote server. Sets Input errors on failure,
     * preventing the service from being canceled.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being canceled (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array
     *  of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {

        if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $response = $this->apiCall(
                $row->meta->host_name,
                $row->meta->user_name,
                $row->meta->port,
                $row->meta->password,
                $row->meta->use_ssl,
                'delete-domain',
                [
                    'domain' => $service_fields->virtualmin_domain,
                    'user' => $service_fields->virtualmin_username,
                ]
            );

            if (isset($response->status) && isset($response->error) && $response->status !== 'success') {
                $this->log($row->meta->host_name . '|delete-domain', serialize($response), 'output');
                $this->Input->setErrors(array(array($response->error)));
            } else {
                $this->log($row->meta->host_name . '|delete-domain', serialize($response), 'output', true);
            }
        }
        return null;
    }

    /**
     * Updates the package for the service on the remote server. Sets Input
     * errors on failure, preventing the service's package from being changed.
     *
     * @param stdClass $package_from A stdClass object representing the current package
     * @param stdClass $package_to A stdClass object representing the new package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being changed (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array
     *  of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function changeServicePackage(
        $package_from,
        $package_to,
        $service,
        $parent_package = null,
        $parent_service = null
    ) {

        if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $response = $this->apiCall(
                $row->meta->host_name,
                $row->meta->user_name,
                $row->meta->port,
                $row->meta->password,
                $row->meta->use_ssl,
                'modify-domain',
                [
                    'domain' => $service_fields->virtualmin_domain,
                    'template' => $package_to->meta->template,
                    'plan' => $package_to->meta->plan,
                ]
            );

            if (isset($response->status) && isset($response->error) && $response->status !== 'success') {
                $this->log($row->meta->host_name . '|modify-domain', serialize($response), 'output');
                $this->Input->setErrors(array(array($response->error)));
            } else {
                $this->log($row->meta->host_name . '|modify-domain', serialize($response), 'output', true);
            }
        }
        return null;
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the
     * admin interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info
     */
    public function getAdminServiceInfo($service, $package)
    {
        $row = $this->getModuleRow();

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('admin_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        return $this->view->fetch();
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the
     * client interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info
     */
    public function getClientServiceInfo($service, $package)
    {
        $row = $this->getModuleRow();

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        return $this->view->fetch();
    }

    /**
     * Statistics tab (disk usage)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabStats($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_stats', 'default');
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $stats = $this->getStats($package, $service);

        $this->view->set('stats', $stats);

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);
        return $this->view->fetch();
    }

    /**
     * Client Statistics tab (disk usage)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientStats($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_client_stats', 'default');
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $stats = $this->getStats($package, $service);

        $this->view->set('stats', $stats);

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);
        return $this->view->fetch();
    }

    /**
     * Fetches all account stats
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @return stdClass A stdClass object representing all of the stats for the account
     */
    private function getStats($package, $service)
    {
        $row = $this->getModuleRow();

        $service_fields = $this->serviceFieldsToObject($service->fields);
        $response = $this->apiCall(
            $row->meta->host_name,
            $row->meta->user_name,
            $row->meta->port,
            $row->meta->password,
            $row->meta->use_ssl,
            'list-domains',
            [
                'domain' => $service_fields->virtualmin_domain,
            ]
        );
        $stats = [
            'disk_used' => '',
            'disk_limit' => '',
            'maximum_databases' => '',
            'maximum_mailboxes' => '',
            'databases_size' => '',
            'databases_count' => ''
        ];

        if (isset($response->data) && isset($response->status) && $response->status === 'success') {
            $stats['disk_used'] = $response->data[0]->values->server_quota_used[0];
            $stats['disk_limit'] = $response->data[0]->values->server_quota[0];
            $stats['maximum_databases'] = $response->data[0]->values->maximum_databases[0];
            $stats['maximum_mailboxes'] = $response->data[0]->values->maximum_mailboxes[0];
            $stats['databases_size'] = $response->data[0]->values->databases_size[0];
            $stats['databases_count'] = $response->data[0]->values->databases_count[0];
        }

        $this->log($row->meta->host_name . '|list-domains', serialize($response), 'input', true);

        return $stats;
    }

    /**
     * Client Actions (reset password)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientActions($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_client_actions', 'default');
        $this->view->base_uri = $this->base_uri;
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $service_fields = $this->serviceFieldsToObject($service->fields);

        // Perform the password reset
        if (!empty($post['virtualmin_password']) && !empty($post['virtualmin_confirm_password'])) {
            Loader::loadModels($this, ['Services']);
            $data = [
                'virtualmin_password' => (isset($post['virtualmin_password']) ? $post['virtualmin_password'] : null),
                'virtualmin_confirm_password' => (isset($post['virtualmin_confirm_password']) ? $post['virtualmin_confirm_password'] : null)
            ];
            $this->Services->edit($service->id, $data);

            if ($this->Services->errors()) {
                $this->Input->setErrors($this->Services->errors());
            }

            $vars = (object) $post;
        } elseif (!empty($_POST)) {
            $this->Input->setErrors(
                [0 => ['error' => Language::_('Virtualmin.!error.password_valid', true)]]
            );
        }

        $this->view->set('service_fields', $service_fields);
        $this->view->set('service_id', $service->id);
        $this->view->set('vars', (isset($vars)
                ? $vars
                : new stdClass()));

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'virtualmin' . DS);
        return $this->view->fetch();
    }

    /**
     * Validates that the given hostname is valid
     *
     * @param string $host_name The host name to validate
     * @return boolean True if the hostname is valid, false otherwise
     */
    public function validateHostName($host_name)
    {
        $validator = new Server();
        return $validator->isDomain($host_name) || $validator->isIp($host_name);
    }

    /**
     * Validates whether or not the connection details are valid by attempting to fetch
     * the number of accounts that currently reside on the server
     *
     * @return boolean True if the connection is valid, false otherwise
     */
    public function validateConnection($password, $host_name, $user_name, $port, $use_ssl)
    {
        $response = $this->apiCall($host_name, $user_name, $port, $password, $use_ssl, 'list-plans', []);

        if (isset($response->status) && $response->status === 'success') {
            $this->log($host_name . '|list-plans', serialize($response), 'output', true);

            return true;
        }

        $this->log($host_name . '|list-plans', serialize($response), 'output', false);

        return false;
    }

    /**
     * Generates a username from the given host name
     *
     * @param string $host_name The host name to use to generate the username
     * @return string The username generated from the given hostname
     */
    private function generateUsername($host_name)
    {
        // Remove everything except letters and numbers from the domain
        // ensure no number appears in the beginning
        $username = ltrim(preg_replace('/[^a-z0-9]/i', '', $host_name), '0123456789');

        $length = strlen($username);
        $pool = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $pool_size = strlen($pool);

        if ($length < 5) {
            for ($i = $length; $i < 8; $i++) {
                $username .= substr($pool, mt_rand(0, $pool_size - 1), 1);
            }
            $length = strlen($username);
        }

        $username = substr($username, 0, min($length, 8));

        return $username;
    }

    /**
     * Generates a password
     *
     * @param int $min_length The minimum character length for the password (5 or larger)
     * @param int $max_length The maximum character length for the password (14 or fewer)
     * @return string The generated password
     */
    private function generatePassword($min_length = 10, $max_length = 14)
    {
        $pool = 'abcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
        $pool_size = strlen($pool);
        $length = mt_rand(max($min_length, 5), min($max_length, 14));
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= substr($pool, mt_rand(0, $pool_size - 1), 1);
        }

        return $password;
    }

    /**
     * Returns an array of service field to set for the service using the given input
     *
     * @param array $vars An array of key/value input pairs
     * @param stdClass $package A stdClass object representing the package for the service
     * @return array An array of key/value pairs representing service fields
     */
    private function getFieldsFromInput(array $vars, $package)
    {
        $fields = [
            'domain' => isset($vars['virtualmin_domain'])
                ? $vars['virtualmin_domain']
                : null,
            'user' => isset($vars['virtualmin_username'])
                ? $vars['virtualmin_username']
                : null,
            'pass' => isset($vars['virtualmin_password'])
                ? $vars['virtualmin_password']
                : null,
            'plan' => $package->meta->plan,
            'features-from-plan' => '',
            'email' => isset($vars['virtualmin_email'])
                ? $vars['virtualmin_email']
                : null
        ];

        return $fields;
    }

    /**
     * Performs an API Call
     *
     * @param string $host The host to the server
     * @param string $user The user to connect as
     * @param string $port The port of the server
     * @param string $pass The password to authenticate with
     * @param boolean $use_ssl Whether to use https or http
     * @param string $program The program to call, e.g: create-domain,
     *  see: https://www.virtualmin.com/documentation/developer/cli/virtual_servers/
     * @param string $params The parameters required to call a program
     * @return array the response of api call
     */
    private function apiCall($host, $user, $port, $pass, $use_ssl, $program, $params)
    {

        if (!isset($this->Http)) {
            Loader::loadComponents($this, ['Net']);
            $this->Http = $this->Net->create('Http');
        }
        $params['multiline'] = '';
        $url = '';
        if ($use_ssl == 'true') {
            $url .= 'https://';
        } elseif ($use_ssl == 'false') {
            $url .= 'http://';
        }
        $url .= $host . ':' . $port . '/virtual-server/remote.cgi?program='
            . $program . '&json=1&' . http_build_query($params);

        // Make POST request to $post_to, log data sent and received
        $this->Http->setHeader('Accept: application/json');
        $this->Http->setHeader('Authorization: Basic ' . base64_encode($user . ':' . $pass));
        $this->Http->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->Http->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $response = $this->Http->get($url);

        return json_decode($response, false);
    }

    /**
     * Fetches a listing of all account plans configured in VirtualMin for the given server
     *
     * @param stdClass $module_row A stdClass object representing a single server
     * @return array An array of packages in key/value pair
     */
    private function getPlans($module_row)
    {
        $plans = [];

        $response = $this->apiCall(
            $module_row->meta->host_name,
            $module_row->meta->user_name,
            $module_row->meta->port,
            $module_row->meta->password,
            $module_row->meta->use_ssl,
            'list-plans',
            []
        );

        if (isset($response->data)) {
            $this->log($module_row->meta->host_name . '|list-plans', serialize($response), 'output', true);

            foreach ($response->data as $value) {
                $plans[$value->values->name[0]] = $value->values->name[0];
            }
        } else {
            $this->log($module_row->meta->host_name . '|list-plans', serialize($response), 'output', false);
        }

        return $plans;
    }

    /**
     * Fetches a listing of all server templates configured in VirtualMin for the given server
     *
     * @param stdClass $module_row A stdClass object representing a single server
     * @return array An array of packages in key/value pair
     */
    private function getTemplates($module_row)
    {
        $templates = [];

        $response = $this->apiCall(
            $module_row->meta->host_name,
            $module_row->meta->user_name,
            $module_row->meta->port,
            $module_row->meta->password,
            $module_row->meta->use_ssl,
            'list-templates',
            []
        );

        if (isset($response->data)) {
            $this->log($module_row->meta->host_name . '|list-templates', serialize($response), 'output', true);

            foreach ($response->data as $value) {
                $templates[$value->values->name[0]] = $value->values->name[0];
            }
        } else {
            $this->log($module_row->meta->host_name . '|list-templates', serialize($response), 'output', false);
        }

        return $templates;
    }

    /**
     * Builds and returns the rules required to add/edit a module row (e.g. server)
     *
     * @param array $vars An array of key/value data pairs
     * @return array An array of Input rules suitable for Input::setRules()
     */
    private function getRowRules($vars)
    {
        $rules = [
            'server_name' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Virtualmin.!error.server_name_valid', true)
                ]
            ],
            'host_name' => [
                'valid' => [
                    'rule' => [[$this, 'validateHostName']],
                    'message' => Language::_('Virtualmin.!error.host_name_valid', true)
                ]
            ],
            'user_name' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Virtualmin.!error.user_name_valid', true)
                ]
            ],
            'port' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Virtualmin.!error.port_valid', true)
                ]
            ],
            'password' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Virtualmin.!error.password_valid', true)
                ],
                'valid_connection' => [
                    'rule' => [
                        [$this, 'validateConnection'],
                        $vars['host_name'],
                        $vars['user_name'],
                        $vars['port'],
                        isset($vars['use_ssl']) ? $vars['use_ssl'] : false
                    ],
                    'message' => Language::_('Virtualmin.!error.password_valid_connection', true)
                ]
            ]
        ];

        return $rules;
    }

    /**
     * Builds and returns rules required to be validated when adding/editing a package
     *
     * @param array $vars An array of key/value data pairs
     * @return array An array of Input rules suitable for Input::setRules()
     */
    private function getPackageRules($vars)
    {
        $rules = [
            'meta[plan]' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Virtualmin.!error.meta[plan].empty', true)
                ]
            ],
            'meta[template]' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Virtualmin.!error.meta[template].empty', true)
                ]
            ]
        ];

        return $rules;
    }
}
