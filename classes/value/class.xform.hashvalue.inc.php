<?php

/**
 * XForm
 * @author jan.kristinus[at]redaxo[dot]org Jan Kristinus
 * @author <a href="http://www.yakamara.de">www.yakamara.de</a>
 */

class rex_xform_hashvalue extends rex_xform_abstract
{
    function postFormAction()
    {
        ## if source not empty
        if ($this->params['value_pool']['email'][$this->getElement('field')] != '') {
            $salt = $this->getElement('salt');
            $origin = $this->params['value_pool']['email'][$this->getElement(3)];

            $hash = hash($this->getElement('function'), $origin . $salt);

            $this->params['value_pool']['email'][$this->getName()] = $hash;

            if ($this->getElement('no_db') != 'no_db') {
                $this->params['value_pool']['sql'][$this->getName()] = $hash;
            }
        } else {
            ## get current hash vor email
            $this->params['value_pool']['email'][$this->getName()] = $this->getValue();
        }
    }

    function getDescription()
    {
        return 'hashvalue -> Beispiel: hashvalue|name|[title]|field|(md5/sha1/sha512/...)|[salt]|[no_db]';
    }

    function getDefinitions()
    {
        return array(
            'type' => 'value',
            'name' => 'hashvalue',
            'values' => array(
                'name'     => array( 'type' => 'name',    'label' => 'Feld' ),
                'label'    => array( 'type' => 'text',    'label' => 'Bezeichnung'),
                'field'    => array( 'type' => 'text',    'label' => 'Input-Feld'),
                'function' => array( 'type' => 'text',    'label' => 'Algorithmus'),
                'salt'     => array( 'type' => 'text',    'label' => 'Salt'),
                'no_db'     => array( 'type' => 'no_db',  'label' => 'Datenbank'),
            ),
            'description' => 'Erzeug Hash-Wert von anderem Feld und speichert ihn',
            'dbtype' => 'text'
        );
    }
}
