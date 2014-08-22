<?php

/**
 * XForm
 * @author jan.kristinus[at]redaxo[dot]org Jan Kristinus
 * @author <a href="http://www.yakamara.de">www.yakamara.de</a>
 */

class rex_xform_be_manager_relation extends rex_xform_abstract
{
    static $xform_list_values = array();

    protected $relation;

    function enterObject()
    {
        global $REX, $I18N;

        // ---------- CONFIG & CHECK

        $this->relation = array();
        $this->relation['source_table'] = $this->params['main_table']; // "rex_em_data_" wegcutten
        $this->relation['label'] = $this->getElement(2);  // HTML Bezeichnung

        $this->relation['target_table'] = $this->getElement('table'); // Zieltabelle
        $this->relation['target_field'] = $this->getElement('field'); // Zielfield welches angezeigt wird.

        $this->relation['relation_type'] = (int) $this->getElement(5); // select single = 0 / select multiple = 1 / popup single = 2 / popup multiple = 3
        if ($this->relation['relation_type'] > 4) {
            $this->relation['relation_type'] = 0;
        }

        $this->relation['eoption'] = (int) $this->getElement(6); // "Leer" Option

        $this->relation['size'] = (int) $this->getElement(8); // boxsize
        if ($this->relation['size'] < 1) {
            $this->relation['size'] = 5;
        }

        if ($this->relation['eoption'] != 1) {
            $this->relation['eoption'] = 0;
        }
        $this->relation['disabled'] = false;

        // ---------- Datensatz existiert bereits, Values aus verknüpfungstabelle holen
        if ($this->params['main_id'] > 0 && $this->params['send'] == 0) {
            $values = array();
            if (trim($this->getValue()) != '') {
                $values = explode(',', $this->getValue());
            } elseif ($this->getElement('relation_table')) {
                $values = $this->getRelationTableValues();
            } else {
                $vs = rex_sql::factory();
                $vs->debugsql = $this->params['debug'];
                $vs->setQuery('
                    select
                        target_id as id
                    from
                        ' . $REX['TABLE_PREFIX'] . 'xform_relation
                    where
                        source_table="' . $this->relation['source_table'] . '" and
                        source_name="' . $this->getName() . '" and
                        source_id="' . $this->params['main_id'] . '"');
                $v = $vs->getArray();
                if (count($v) > 0) {
                    foreach ($v as $w) {
                        $values[$w['id']] = $w['id'];
                    }
                }
            }
            $this->setValue($values);
            // echo '<pre>++ ';var_dump($this->getValue());echo '</pre>';
        }


        // ---------- connected, fix values
        if (isset($this->params['rex_xform_set'][$this->getName()])) {

            $values = $this->getValue();
            $values[] = $this->params['rex_xform_set'][$this->getName()];
            $this->setValue($values);
            $this->relation['disabled'] = true;
        }


        // ---------- Value angleichen -> immer Array mit IDs daraus machen
        if (!is_array($this->getValue())) {

            if (trim($this->getValue()) == '') {
                $this->setValue(array());
            } else {
                $this->setValue(explode(',', $this->getValue()));
            }
        }

        // ---------- (array) $this->getValue()
        // echo '<hr /><pre>'; var_dump($this->getValue()); echo '</pre>';


        // ---------- check values
        $sql = 'select id,' . mysql_real_escape_string($this->relation['target_field']) . ' from ' . $this->relation['target_table'];
        $options = array();
        $valueName = '';
        $values = array();
        if (count($this->getValue()) > 0) {
            $add_sql = array();
            foreach ($this->getValue() as $v) {
                $add_sql[] = ' id=' . intval($v) . '';
            }
            if (count($add_sql) > 0) {
                $sql .= ' where ' . implode(' OR ', $add_sql);
            }

            $vs = rex_sql::factory();
            $vs->debugsql = $this->params['debug'];
            $vs->setQuery($sql);
            foreach ($vs->getArray() as $v) {
                $options[$v['id']] = $v[$this->relation['target_field']] . ' [id=' . $v['id'] . ']';
                $valueName = $v[$this->relation['target_field']];
            }
            foreach ($this->getValue() as $v) {
                if (isset($options[$v])) {
                    $values[] = $v;
                }
            }

            $this->setValue($values);
        }

        // ---------- (array) $this->getValue()
        // echo '<pre>'; var_dump($this->getValue()); echo '</pre>';


        // ---------- empty option ?

        if ($this->params['send'] == 1 && $this->relation['eoption'] == 0 && count($this->getValue()) == 0) {
            // Error. Fehlermeldung ausgeben
            $this->params['warning'][] = $this->params['error_class'];
            $this->params['warning_messages'][] = $this->getElement(7);
        }

        // --------------------------------------- Selectbox, single 0 or multiple 1

        if ($this->relation['relation_type'] < 2) {

            // ----- SELECT BOX
            $options = array();
            if ($this->relation['relation_type'] == 0 && $this->relation['eoption'] == 1) {
                $options[''] = '-';
            }
            foreach (self::getListValues($this->relation['target_table'], $this->relation['target_field']) as $id => $name) {
                if (strlen($name) > 50) {
                    $name = substr($name, 0, 45) . ' ... ';
                }
                $options[$id] = $name . ' [id=' . $id . ']';
            }

            $this->params['form_output'][$this->getId()] = $this->parse('value.be_manager_relation.tpl.php', compact('options'));

        }


        // ------------------------------------ POPUP, single, multiple 1-1, n-m

        if ($this->relation['relation_type'] == 2 || $this->relation['relation_type'] == 3) {

            $link = 'index.php?page=xform&subpage=manager&tripage=data_edit&table_name=' . $this->relation['target_table'];
            $this->params['form_output'][$this->getId()] = $this->parse('value.be_manager_relation.tpl.php', compact('valueName', 'options', 'link'));

        }


        // --------------------------------------- POPUP, 1-n

        if ($this->relation['relation_type'] == 4) {

            $link = 'index.php?page=xform&subpage=manager&tripage=data_edit&table_name=' . $this->relation['target_table'] . '&rex_xform_filter[' . $this->relation['target_field'] . ']=' . $this->params['main_id'] . '&rex_xform_set[' . $this->relation['target_field'] . ']=' . $this->params['main_id'];
            $this->params['form_output'][$this->getId()] = $this->parse('value.be_manager_relation.tpl.php', compact('valueName', 'options', 'link'));

        }


        // --------------------------------------- save

        $this->params['value_pool']['email'][$this->getName()] = stripslashes(implode(',', $this->getValue()));
        if (!$this->getElement('relation_table')) {
            $this->params['value_pool']['sql'][$this->getName()] = implode(',', $this->getValue());
        }
    }




    // -------------------------------------------------------------------------

    /*
     * postAction wird nach dem Speichern ausgef�hrt
     * hier wird entsprechend der entities
     */
    function postAction()
    {
        global $REX;

        if (!$relationTable = $this->getElement('relation_table')) {
            return;
        }

        // $this->params["debug"] = TRUE;

        $source_id = -1;
        if (isset($this->params['value_pool']['email']['ID']) && $this->params['value_pool']['email']['ID'] > 0) {
            $source_id = (int) $this->params['value_pool']['email']['ID'];
        }
        if ($source_id < 1 && isset($this->params['main_id']) && $this->params['main_id'] > 0) {
            $source_id = (int) $this->params['main_id'];
        }

        if ($source_id < 1 || $this->params['main_table'] == '') {
            return false;
        }

        $relationTableField = $this->getRelationTableFields();
        if (!$relationTableField['source'] || !$relationTableField['target']) {
            return;
        }

        // ----- Value angleichen -> immer Array mit IDs daraus machen
        $values = array();
        if (!is_array($this->getValue())) {
            if (trim($this->getValue()) != '') {
                $values = explode(',', $this->getValue());
            }
        } else {
            $values = $this->getValue();
        }
        $values = array_map('intval', $values);

        $sql = rex_sql::factory();
        $sql->debugsql = $this->params['debug'];
        $relationTablePreEditValues = $this->getRelationTableValues();
        foreach ($values as $value) {
            if (!isset($relationTablePreEditValues[$value])) {
                $sql->setTable($relationTable);
                $sql->setValue($relationTableField['source'], $source_id);
                $sql->setValue($relationTableField['target'], $value);
                $sql->insert();
            }
        }
        $sql->flushValues();
        $sql->setTable($relationTable);
        $sql->setWhere('`' . $sql->escape($relationTableField['source']) . '`=' . $source_id . ' AND `' . $sql->escape($relationTableField['target']) . '` NOT IN (' . implode(',', $values) . ')');
        $sql->delete();


        /*$d = rex_sql::factory();
        $d->debugsql = $this->params['debug'];
        $d->setQuery('delete from ' . $REX['TABLE_PREFIX'] . 'xform_relation where source_table="' . $this->be_em['source_table'] . '" and source_name="' . $this->getName() . '" and source_id="' . $source_id . '"');

        if (count($values) > 0) {
            $i = rex_sql::factory();
            $i->debugsql = $this->params['debug'];
            foreach ($values as $v) {
                $i->setTable($REX['TABLE_PREFIX'] . 'xform_relation');
                $i->setValue('source_table', $this->relation['source_table']);
                $i->setValue('source_name', $this->getName());
                $i->setValue('source_id', $source_id);
                $i->setValue('target_table', $this->relation['target_table']);
                $i->setValue('target_id', $v);
                $i->insert();
            }

        }*/

    }

    // -------------------------------------------------------------------------


    /*
     * Allgemeine Beschreibung
     */
    function getDescription()
    {
        // label,bezeichnung,tabelle,tabelle.feld,relationstype,style,no_db
        // return "be_em_relation -> Beispiel: ";
        return '';
    }

    function getDefinitions()
    {
        return array(
            'type' => 'value',
            'name' => 'be_manager_relation',
            'values' => array(
                'name'         => array( 'type' => 'name',    'label' => 'Name' ),
                'label'        => array( 'type' => 'text',    'label' => 'Bezeichnung'),
                'table'        => array( 'type' => 'table',   'label' => 'Ziel Tabelle'),
                'field'        => array( 'type' => 'text',    'label' => 'Ziel Tabellenfeld zur Anzeige oder Zielfeld'),
                'type'         => array( 'type' => 'select',  'label' => 'Mehrfachauswahl', 'default' => '', 'definition' => array('0' => 'select (single)', '1' => 'select (multiple)', '2' => 'popup (single)', '3' => 'popup (multiple)' , '4' => 'popup (multiple 1-n)') ), // ,popup (multiple / relation)=4
                'empty_option' => array( 'type' => 'boolean', 'label' => 'Mit "Leer-Option"' ),
                'empty_value'  => array( 'type' => 'text',    'label' => 'Fehlermeldung wenn "Leer-Option" nicht aktiviert ist.'),
                'size'         => array( 'type' => 'text', 'name' => 'boxheight',    'label' => 'Höhe der Auswahlbox'),
                'relation_table' => array( 'type' => 'table', 'label' => 'Relationstabelle', 'empty_option' => 1),
            ),
            'description' => 'Hiermit kann man Verkn&uuml;pfungen zu anderen Tabellen setzen',
            'dbtype' => 'text',
            'hooks' => array(
                'preCreate' => function ($field) {
                    return empty($field['relation_table']) && '4' != $field['type'];
                }
            ),
        );
    }

    static function getListValue($params)
    {
        // TODO Relation table berücksichtigen

        $listValues = self::getListValues($params['params']['field']['table'], $params['params']['field']['field']);
        $return = array();
        foreach (explode(',', $params['value']) as $value) {
            if (isset($listValues[$value])) {
                $return[] = $listValues[$value];
            }
        }

        return implode('<br />', $return);
    }

    private static function getListValues($table, $field)
    {
        if (!isset(self::$xform_list_values[$table][$field])) {
            self::$xform_list_values[$table][$field] = array();
            if ($relation = rex_xform_manager_table::getRelation($table, $field)) {
                $relationListValues = self::getListValues($relation['table'], $relation['field']);
            }
            $db = rex_sql::factory();
            $db_array = $db->getArray('select id, `' . $db->escape($field) . '` as name from `' . $db->escape($table) . '` ORDER BY `' . $db->escape($field) . '`');
            foreach ($db_array as $entry) {
                if ($relation && isset($relationListValues[$entry['name']])) {
                    self::$xform_list_values[$table][$field][$entry['id']] = $relationListValues[$entry['name']];
                } else {
                    self::$xform_list_values[$table][$field][$entry['id']] = $entry['name'];
                }
            }
        }
        return self::$xform_list_values[$table][$field];
    }

    protected function getRelationTableFields()
    {
        return rex_xform_manager_table::getRelationTableFields(
            $this->getElement('relation_table'),
            $this->params['main_table'],
            $this->getElement('table')
        );
    }

    protected function getRelationTableValues()
    {
        $values = array();
        $relationTableFields = $this->getRelationTableFields();
        if ($relationTableFields['source'] && $relationTableFields['target']) {
            $sql = rex_sql::factory();
            $sql->debugsql = $this->params['debug'];
            $sql->setQuery('
                SELECT `' . $sql->escape($relationTableFields['target']) . '` as id
                FROM `' . $sql->escape($this->getElement('relation_table')) . '`
                WHERE `' . $sql->escape($relationTableFields['source']) . '` = ' . (int) $this->params['main_id']
            );
            while ($sql->hasNext()) {
                $id = $sql->getValue('id');
                $values[$id] = $id;
                $sql->next();
            }
        }
        return $values;
    }

}
