<?php
/*
   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

*/
class UserResourceTypePlugin extends MantisPlugin {
    
    var $whoami;
    var $fieldsKeys;
    var $inputs;
    var $inputPrefix;

    function register() {
        $this->name = plugin_lang_get( 'plugin_title' );
        $this->description = plugin_lang_get( 'plugin_description' );
        $this->page = '';

        $this->version = '1.0.0';
        $this->requires = array(
            'MantisCore' => '2.0.0',
        );

        $this->author = 'Francisco Mancardi ';
        $this->contact = '';
        $this->url = '';
    }

    function hooks() {
        return array(
            'EVENT_MANAGE_USER_CREATE_FORM' => 'manageUserCreateForm',
            'EVENT_MANAGE_USER_CREATE' => 'saveResourceType',
            'EVENT_MANAGE_USER_UPDATE_FORM' => 'manageUserUpdateForm',
            'EVENT_MANAGE_USER_UPDATE' => 'saveResourceType',
        );
    }

    /**
     *
     */
    function config() {
        $cfg = self::getConfig();

        $this->whoami = str_replace('Plugin','',__CLASS__);
        $this->inputPrefix = $this->whoami . '_';

        $this->fieldsKeys = array();
        foreach($cfg['typeHierarchy'] as $key => $conf ) {
            $this->fieldsKeys[$conf['inKey']] = $conf['inKey']; 
        }
        
        $this->inputs = $this->fieldsKeys; 
        foreach($this->inputs as $t_ty => $t_vv ) {
            $this->inputs[$t_ty] =  $this->inputPrefix . $t_ty;
        }


        return $cfg;
    }

    /**
     *
     */
    function init() {
    }


    /**
     *
     */
    static function getConfig() {
        $cfg = array();

        $cfg['typeHierarchy'] = 
          array('mainRType' => array('id' => 1, 'inKey' => 'mainRType'),
                'secRType' => array('id' => 2, 'inKey' => 'secRType'));

        return $cfg;
    }

    /**
     *
     *
     */ 
    function manageUserCreateForm( $p_event, $p_user_id = null ) {
        $this->resourceTypesInputs();
    }

    /**
     *
     *
     */
    function manageUserUpdateForm( $p_event, $p_user_id = null ) {
        $this->resourceTypesInputs($p_user_id,'edit');
    }


    /**
     *
     *
     */
    function resourceTypesInputs( $p_user_id = null, $p_operation = null ) {
        
        echo __FUNCTION__;

        $typeHierarchy = plugin_config_get('typeHierarchy');

        $attr = array();
        switch( $p_operation ) {
            case 'edit':
                $str_open = $str_close = '';
                $table = plugin_table( 'links' );
                $t_query = " SELECT * FROM {$table} 
                             WHERE user_id=" . db_param();

                $t_sql_param = array( $p_user_id );
                $t_result = db_query( $t_query, $t_sql_param);
                
                if( db_affected_rows() == 0 ) {
                  // DB not OK
                  return;
                }  

                while( $t_row = db_fetch_array( $t_result ) ) {
                    foreach( $typeHierarchy as $key => $conf ) {
                        if( $conf['id'] == $t_row['link_type'] ) {
                            $attr[$conf['inKey']] = $t_row['resource_id'];
                            break;
                        }
                    }
                }
            break;

            case 'create':
            default:
                $str_open = ' <p><table class="table table-bordered ' .
                            ' table-condensed table-striped">' . '<fieldset>';
                $str_close = '</fieldset></table>';
                $attr = array();
                foreach( $typeHierarchy as $key => $conf ) {
                    $attr[$conf['inKey']] = '';
                }
            break;
        }
        
        echo $str_open;
        foreach($attr as $key => $val) {
            $xx = array($key => $val);
            $this->draw_resource_type_combo_row( $xx );
        }
        echo $str_close;
    }

    /**
     *
     */
    function draw_resource_type_combo_row( $attr ) {
      $t_search = array('draw_','_row');
      $t_replace = array('get_','');
      $t_fn = str_replace($t_search, $t_replace, __FUNCTION__);
      $items = $this->$t_fn();
      
      $t_inkey = key($attr);
      $opt = array('input_name' => $this->inputs[$t_inkey], 
                   'suffix' => '');
      helperMethodsPlugin::drawComboRow($this->fieldsKeys[$t_inkey],
        $items,$attr,$opt);
    }

    /**
     *
     */
    function get_resource_type_combo() {
      $t_table = plugin_table('domain');
      $t_rs = $this->getTableComboI18N($t_table,true);
      return $t_rs;
    }

   /**
    *
    */
    function getTableComboI18N($p_table,$opt_blank=false) {
        $t_debug = '/* ' . __METHOD__ . ' */ ';

        $t_rs = array();
        $t_query = $t_debug . " SELECT id, label FROM $p_table ";
        $t_result = db_query( $t_query, array());
    
        if($opt_blank) {
          $t_rs[''] = ''; 
        }
        
        while ( $t_row = db_fetch_array( $t_result ) ) {
            $t_rs[$t_row['id']] = plugin_lang_get($t_row['label']); 
        }
        natsort($t_rs);

        return $t_rs;
    }

   /**
    *
    */
    function saveResourceType( $p_event, $p_user_id ) {

        $table = plugin_table('links');
        db_param_push();
        $t_query = " SELECT user_id,resource_id,link_type 
                     FROM {$table} WHERE user_id=" . db_param();
        $t_result = db_query( $t_query, array( $p_user_id ) );

        if( db_affected_rows() == 0 ) {
          $this->insertLinks( $p_user_id );
        } else {
          $this->updateLinks( $p_user_id, $t_result );          
        }
        
    }

    /**
     *
     */
    function getUserInput() {      
        $userInput = array();
        foreach($this->inputs as $t_ty => $t_vv ) {  
            $accessKey = $this->inputPrefix . $t_ty;

            $userInput[$t_ty] = 0;  
            if( isset($_REQUEST[$accessKey]) ) {
                $userInput[$t_ty] = intval($_REQUEST[$accessKey]);
            }         
        }
        return $userInput;
    }

    /**
     *
     */
    function insertLinks($p_user_id) {
        $table = plugin_table('links');
        $t_query = " INSERT INTO {$table} 
                     (user_id,resource_id,link_type)
                     VALUES(" . db_param() . ',' . 
                                db_param() . ',' . db_param() . ") ";

        $typeHierarchy = plugin_config_get('typeHierarchy');
        $userInput = $this->getUserInput();

        foreach( $userInput as $linkType => $resourceType ) {
            $linkTypeID = $typeHierarchy[$linkType]['id'];
            $t_sql_param = array($p_user_id,$resourceType,$linkTypeID);
            db_query( $t_query, $t_sql_param );
        } 
    }

    /**
     *
     */
    function updateLinks($p_user_id, $p_db_result = null) {

        $table = plugin_table('links');
        $typeHier = plugin_config_get('typeHierarchy');
        $userInput = $this->getUserInput();

        $t_query = " UPDATE {$table}
                     SET resource_id = " . db_param() .
                   " WHERE link_type=" . db_param() .  
                   " AND user_id=" . db_param();

        while( $t_row = db_fetch_array( $p_db_result ) ) {
            foreach($userInput as $accessKey => $value) {
              if( $typeHier[$accessKey]['id'] ==  $t_row['link_type'] ) {
                $t_sql_params = array();  
                $t_sql_params = 
                    array($value,intval($t_row['link_type']),$p_user_id);
                db_query($t_query, $t_sql_params);
              }  
            }  
        }
    }

   /** 
    *  Schema
    * 
    */
    function schema() {
      $t_schema = array();

      // Domain Table
      $t_table = plugin_table( 'domain' );
      $t_ddl = " id  I   NOTNULL UNSIGNED PRIMARY AUTOINCREMENT," .
               " label C(200) NOTNULL";

      $t_schema[] = array( 'CreateTableSQL',
                           array( $t_table, $t_ddl) );

      $t_schema[] = array( 'CreateIndexSQL', 
                           array( 'idx_domain', $t_table, 
                            'label', array( 'UNIQUE' ) ) );
    
      // 
      $t_table = plugin_table( 'links' );
      $t_ddl = " id  I   NOTNULL UNSIGNED PRIMARY AUTOINCREMENT,
                 link_type I   UNSIGNED NOTNULL DEFAULT '0',
                 user_id I   UNSIGNED NOTNULL DEFAULT '0',
                 resource_id I   UNSIGNED NOTNULL DEFAULT '0' ";

      $t_schema[] = array( 'CreateTableSQL',
                           array($t_table , $t_ddl) );

      $t_schema[] = array( 'CreateIndexSQL', 
                           array( 'idx_user_link_type', 
                                  $t_table, 
                                 'user_id,link_type', array( 'UNIQUE' ) ) );

      // StartUp Data
      $t_table = plugin_table( 'domain' );
      $t_schema[] =
      array( 'InsertData', 
            array( $t_table, 
              "( id, label )
               VALUES
               ( '1', 'Product_Developer' )" ) );

      $t_schema[] =
      array( 'InsertData', 
            array( $t_table, 
              "( id, label )
               VALUES
               ( '2', 'Product_Technical_Leader' )" ) );

      $t_schema[] =
      array( 'InsertData', 
            array( $t_table, 
              "( id, label )
               VALUES
               ( '3', 'Product_Development_Engineer' )" ) );

      $t_schema[] =
      array( 'InsertData', 
            array( $t_table, 
              "( id, label )
               VALUES
               ( '4', 'Product_Development_Manager' )" ) );

      $t_schema[] =
      array( 'InsertData', 
            array( $t_table, 
              "( id, label )
               VALUES
               ( '5', 'Product_Technical_Consultant' )" ) );


      return $t_schema;
    } 

} 