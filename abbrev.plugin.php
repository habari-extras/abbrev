<?php
class Abbrev extends Plugin {

    const GUID         = '490e4c1b-67b8-4c41-b290-07ae5967b785';
    const PLUGIN_NAME  = 'Abbrev';
    const PLUGIN_TOKEN = 'Abbrev_token';
    const VERSION      = '0.1alpha';

    private function _getAbbrevs() {
        return DB::get_results('SELECT * FROM ' . DB::table('abbrev')
                               . ' ORDER BY priority ASC, '
                               . 'LENGTH(abbrev) DESC, '
                               . 'abbrev ASC');
    }

//    public function info() {
//        return array(
//            'name'        => self::PLUGIN_NAME,
//            'version'     => self::VERSION,
//            'url'         => 'http://habariproject.org/',
//            'author'      => 'Ken Coar',
//            'authorurl'   => 'http://Ken.Coar.Org/',
//            'license'     => 'Apache License 2.0',
//            'description' => 'Extensible abbreviations'
//            );
//    }

    public function action_admin_header($theme) {
        $vars = Controller::get_handler_vars();
        if (($theme->page == 'plugins')
            && isset($vars['configure'])
            && ($this->plugin_id == $vars['configure'])) {
            Stack::add('admin_stylesheet',
                       array($this->get_url() . '/abbrev.css',
                             'screen'),
                       'abbrev',
                       array('admin'));
            $abbrevs = $this->_getAbbrevs();
            if ($n = count($abbrevs)) {
                $js_xid2def .= "\n  \$(document).ready(function(){\n"
                    .   "    aDefs=[];\n";
                foreach ($abbrevs as $abbrev) {
                    $prefix = "'" . $abbrev->prefix . "'";
                    $postfix = "'" . $abbrev->postfix . "'";
                    $prefix = str_replace('\\', '\\\\', $prefix);
                    $prefix = str_replace('"', '\\"', $prefix);
                    $postfix = str_replace('\\', '\\\\', $postfix);
                    $postfix = str_replace('"', '\\"', $postfix);
                    $js_xid2def .= '    aDefs[' . $abbrev->xid . '] = ['
                        . ($abbrev->caseful ? 'true' : 'false') . ', '
                        . HTMLentities($prefix) . ', '
                        . HTMLentities($postfix) . ', '
                        . '"' . HTMLentities($abbrev->definition) . "\"];\n";
                }
                $js_xid2def .= "    \$('#mAbbrev select')"
                    . ".change(function(){\n"
                    . "      aNum = \$(this).val();\n"
                    . "      \$('#mDefinition input').val($('<input value=\"' + aDefs[aNum][3] + '\"/>').val());\n"
                    . "      \$('#mCaseful input[type=\"checkbox\"]')"
                    . ".attr('checked', aDefs[aNum][0]);\n"
                    . "      \$('#mPreRegex input[type=\"text\"]')"
                    . ".val($('<input value=\"' + aDefs[aNum][1] + '\"/>').val());\n"
                    . "      \$('#mPostRegex input[type=\"text\"]')"
                    . ".val($('<input value=\"' + aDefs[aNum][2] + '\"/>').val())});\n"
                    . "  })\n";
                Stack::add('admin_header_javascript',
                           $js_xid2def,
                           'abbrev',
                           'admin');
            }
        }
    }

    public function action_update_check() {
        Update::add(self::PLUGIN_NAME, self::GUID, $this->info->version);
    }

    /*
     * Admin-type methods
     */
    public function action_plugin_activation($file) {
        DB::register_table('abbrev');
        /*
         * Create the database table, or upgrade it
         */
        $dbms = DB::get_driver_name();
        $sql = 'CREATE TABLE ' . DB::table('abbrev') . ' '
            . '(';
        if ($dbms == 'sqlite') {
            $sql .= 'xid INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,';
        }
        else if ($dbms == 'mysql') {
            $sql .= 'xid INT(9) NOT NULL AUTO_INCREMENT,'
                . 'UNIQUE KEY xid (xid),';
        }
        else {
            $sql .= 'xid INT(9) NOT NULL AUTO_INCREMENT,'
                . 'UNIQUE KEY xid (xid),';
        }
        $sql .= 'abbrev VARCHAR(255),'
            . 'caseful INTEGER DEFAULT 0,'
            . "prefix VARCHAR(16) DEFAULT '\\b',"
            . "postfix VARCHAR(16) DEFAULT '\\b',"
            . 'priority INTEGER DEFAULT 100,'
            . 'definition VARCHAR(255)'
            . ')';
        if (! DB::dbdelta($sql)) {
//            Utils::debug(DB::get_errors());
        }
        if ($file == str_replace('\\', '/', $this->get_file())) {
            ACL::create_token(self::PLUGIN_TOKEN,
                              _t('Allow use of Abbrev plugin'),
                              'Category',
                              false);
            $group = UserGroup::get_by_name('admin');
            $group->grant(self::PLUGIN_TOKEN);
        }
    }

    public function action_plugin_deactivation($file) {
        if ($file == str_replace('\\', '/', $this->get_file())) {
            ACL::destroy_token(self::PLUGIN_TOKEN);
        }
    }

    /*
     * Add a configuration panel for us.
     */
    public function filter_plugin_config($actions, $plugin_id) {
        if ($plugin_id == $this->plugin_id()) {
            $actions[] = _t('Configure');
        }
        return $actions;
    }
    /*
     * And here's the actual configuration panel itself.
     */

    public function action_plugin_ui($plugin_id, $action) {
        if ($plugin_id == $this->plugin_id()) {
            $abbrevs = $this->_getAbbrevs();
            switch ($action) {
            case _t('Configure'):
                $ui = new FormUI(strtolower(get_class($this)));
                $ui->append('fieldset', 'setAdd', 'Add an abbreviation');
                $setAdd = $ui->setAdd;
                $setAdd->class = 'abbrev-settings';

                /*
                 * Fields to add an abbreviation.
                 */
                $setAdd->append(new FormControlText('nAbbrev',
                                                    null,
                                                    _t('Abbreviation:')));
                $setAdd->nAbbrev->value = '';

                $setAdd->append(new FormControlText('nDefinition',
                                                    null,
                                                    _t('Definition:')));
                $setAdd->nDefinition->value = '';
                $setAdd->nDefinition->size = 50;

                $setAdd->append('checkbox',
                                'nCaseful',
                                '1',
                                'Case-sensitive:');
                $setAdd->append('fieldset',
                                'setAddAdvanced',
                                'Advanced options');
                $setAdv = $setAdd->setAddAdvanced;
                $setAdv->append(new FormControlText('nPreRegex',
                                                    null,
                                                    _t('Left boundary regex:')));
                $setAdv->nPreRegex->size = 8;
                $setAdv->nPreRegex->value = '\b';

                $setAdv->append(new FormControlText('nPostRegex',
                                                    null,
                                                    _t('Right boundary regex:')));
                $setAdv->nPostRegex->size = 8;
                $setAdv->nPostRegex->value = '\b';

                /*
                 * Only allow editing and deletion if we already have
                 * some abbreviations defined.
                 */
                if ($n = count($abbrevs)) {
                    $anames = array();
                    $adefs = array();
                    foreach ($abbrevs as $abbrev) {
                        $anames[$abbrev->abbrev] = $abbrev->xid;
                        $adefs[$abbrev->abbrev] = $abbrev->definition;
                    }

                    /*
                     * First, the modification stuff.
                     */
                    $ui->append('fieldset', 'setModify',
                                'Modify an abbreviation');
                    $setModify = $ui->setModify;
                    $setModify->class = 'abbrev-settings';

                    $setModify->append(new FormControlSelect('mAbbrev',
                                                             null,
                                                             _t('Select abbreviation to modify')));
                    $setModify->mAbbrev->size = 1;
                    $setModify->mAbbrev->options = array_flip($anames);

                    $setModify->append(new FormControlText('mDefinition',
                                                           null,
                                                           _t('Definition:')));
                    $setModify->mDefinition->value = '';
                    $setModify->mDefinition->size = 50;

                    $setModify->append('checkbox',
                                       'mCaseful',
                                       '1',
                                       'Case-sensitive:');

                $setModify->append('fieldset',
                                   'setModAdvanced',
                                   'Advanced options');
                $setAdv = $setModify->setModAdvanced;
                $setAdv->append(new FormControlText('mPreRegex',
                                                    null,
                                                    _t('Left boundary regex:')));
                $setAdv->mPreRegex->size = 8;
                $setAdv->mPreRegex->value = '';

                $setAdv->append(new FormControlText('mPostRegex',
                                                    null,
                                                    _t('Right boundary regex:')));
                $setAdv->mPostRegex->size = 8;
                $setAdv->mPostRegex->value = '';

                    /*
                     * Now the deletion stuff.
                     */
                    $ui->append('fieldset', 'setDelete',
                                'Delete abbreviations');
                    $setDelete = $ui->setDelete;
                    $setDelete->class = 'abbrev-settings';

                    foreach ($anames as $abbrev => $xid) {
                        $setDelete->append('checkbox',
                                           'abbrev_' . $xid,
                                           $xid,
                                           $abbrev);
                    }
                }

                $ui->append('submit', 'save', 'Save');
                $ui->on_success(array($this, 'handle_config_form'));
                $ui->out();
                break;
            }
        }
    }

    public function handle_config_form($ui) {
        $abbrevs = $this->_getAbbrevs();
        $abbrevById = array();
        foreach ($abbrevs as $abbrev) {
            $abbrevById[$abbrev->xid] = $abbrev;
        }

        $setAdd = $ui->setAdd;
        $setAddAdv = $setAdd->setAddAdvanced;
        $setMod = $ui->setModify;
        $setModAdv = $setMod->setModAdvanced;
        $setDel = $ui->setDelete;
        if ($ui->nAbbrev->value) {
            $prefix = $setModAdv->nPreRegex->value;
            $prefix = html_entity_decode($prefix);
            $prefix = str_replace('\\', '\\\\', $prefix);
            $postfix = $setModAdv->nPostRegex->value;
            $postfix = html_entity_decode($postfix);
            $postfix = str_replace('\\', '\\\\', $postfix);
            DB::insert(DB::table('abbrev'),
                       array('caseful'    => $setAdd->nCaseful->value ? 1 : 0,
                             'abbrev'     => $setAdd->nAbbrev->value,
                             'definition' => $setAdd->nDefinition->value,
                             'prefix'     => $prefix,
                             'postfix'    => $postfix));
//            Utils::debug(DB::get_errors());
        }

        /*
         * Modify an abbreviation.
         */
        $xid = $setMod->mAbbrev->value;
        $caseful = $setMod->mCaseful->value ? 1 : 0;
        $def = $setMod->mDefinition->value;
        $prefix = $setModAdv->mPreRegex->value;
        $prefix = str_replace('\\', '\\\\', $prefix);
        $postfix = $setModAdv->mPostRegex->value;
        $postfix = str_replace('\\', '\\\\', $postfix);
        $prefix = html_entity_decode($prefix);
        $postfix = html_entity_decode($postfix);
//        Utils::debug(array($def, $prefix, $postfix, $abbrevById[$xid]));
        if ($def
            && (($def != $abbrevById[$xid]->definition)
                || ($setMod->mCaseful->value != $abbrevById[$xid]->caseful)
                || ($prefix != $abbrevById[$xid]->prefix)
                || ($postfix != $abbrevById[$xid]->postfix))) {
            DB::update(DB::table('abbrev'),
                       array('definition' => $def,
                             'caseful' => $caseful,
                             'prefix' => $prefix,
                             'postfix' => $postfix),
                       array('xid' => $xid));
//            Utils::debug(DB::get_errors());
        }

        /*
         * Delete some?
         */
        $a_delboxes = $setDel->controls;
        foreach ($a_delboxes as $id => $formctl) {
            if ($formctl->value) {
                preg_match('/^abbrev_(\d+)$/', $id, $pieces);
                DB::delete(DB::table('abbrev'), array('xid' => $pieces[1]));
//                Utils::debug(DB::get_errors());
            }
        }

        $ui->save;
        return false;
    }

    public function action_init() {
        DB::register_table('abbrev');
        Stack::add('template_stylesheet',
                   array($this->get_url() . '/abbrev.css', 'screen'),
                   'abbrev');
        Stack::add('template_header_javascript',
                   $this->get_url() . '/abbrev.js',
                   'abbrev');
    }

    private function sequester_abbrevs($content, &$saved_abbrevs) {
        $redelim = chr(165);
        $regex = $redelim . '(<abbr[^>]*>.*?</abbr>)' . $redelim . 'siS';
        while (preg_match($regex, $content, $matched)) {
            $saved_abbrevs[] = $matched[1];
            $content = preg_replace('�\Q' . $matched[1] . '\E�s',
                                    "<!-- SAVED_ABBREV -->"
                                    . count($saved_abbrevs)
                                    . "<!-- /SAVED_ABBREV -->",
                                    $content);
        }
        return $content;
    }

    /*
     * Do the actual replacement of any abbreviations.  Don't make any
     * changes to text inside tags!
     */
    public function filter_post_content_out($content, $post) {
        $redelim = chr(165);
        /*
         * These should really be sorted longest-first so that a short
         * abbreviation doesn't break a longer one.
         */
        $abbrevs = $this->_getAbbrevs();
        $content = " $content ";
        $saved_abbrev = array();
        $saved_markup = array();
        /*
         * Excise any existing abbreviations so we don't double up.
         */
        $content = $this->sequester_abbrevs($content, $saved_abbrevs);
        /*
         * Likewise for any markup tags so we don't insert into the
         * middle of one.
         */
        $regex = $redelim . '(<[^!][^>]*>)' . $redelim . 'siS';
        while (preg_match($regex, $content, $matched)) {
            $saved_markup[] = $matched[1];
            $content = preg_replace('�\Q' . $matched[1] . '\E�s',
                                    "<!-- SAVED_MARKUP -->"
                                    . count($saved_markup)
                                    . "<!-- /SAVED_MARKUP -->",
                                    $content);
        }
        foreach ($abbrevs as $abbrev) {
            /*
             * Check to see if the abbrev text occurs; use strstr() to
             * avoid the overhead of using PCRE for things that won't
             * be found.
             */
            if ($abbrev->caseful) {
                /*
                 * If it's case-sensitive, see if it occurs.
                 */
                if (! strstr($content, $abbrev->abbrev)) {
                    continue;
                }
                else {
                    $reflags = 's';
                }
            }
            else {
                /*
                 * Do the case-insensitive one.
                 */
                if (! stristr($content, $abbrev->abbrev)) {
                    continue;
                }
                else {
                    $reflags = 'si';
                }
            }
            $pattern = sprintf('%s(?<=%s)(\Q%s\E)(?=%s)%s%s',
                               $redelim,
                               $abbrev->prefix,
                               $abbrev->abbrev,
                               $abbrev->postfix,
                               $redelim,
                               $reflags);
            $content = preg_replace($pattern,
                                   '<abbr title="'
                                   . $abbrev->definition
                                   . "\">$1</abbr>",
                                   $content);
            $content = $this->sequester_abbrevs($content, $saved_abbrev);
        }
        /*
         * Now restore any saved strings
         */
        for ($i = 1; $i <= count($saved_abbrev); $i++) {
            $regex = sprintf('%s<!-- SAVED_ABBREV -->%s<!-- /SAVED_ABBREV -->%ss',
                             $redelim, $i, $redelim);
            $content = preg_replace($regex,
                                    $saved_abbrev[$i - 1],
                                    $content);
        }
        for ($i = 1; $i <= count($saved_markup); $i++) {
            $regex = sprintf('%s<!-- SAVED_MARKUP -->%s<!-- /SAVED_MARKUP -->%ss',
                             $redelim, $i, $redelim);
            $content = preg_replace($regex,
                                    $saved_markup[$i - 1],
                                    $content);
        }
        $content = trim($content);
        return $content;
    }

    /*
     * Code for updating an abbreviation:
     *
     * DB::update(
     *            DB::table('abbrev'),
     *            $version_vals,
     *            array('version' => $version_vals['version'],
     *                  'post_id' => $post->id)
     *           );
     */

}
/*
 * Local Variables:
 * mode: C
 * c-file-style: "bsd"
 * tab-width: 4
 * indent-tabs-mode: nil
 * End:
 */
?>