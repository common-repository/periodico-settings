<?php
/*
Plugin Name: Atomic Settings
Plugin URI:
Description: Allows you to adjust settings for the Atomic theme
Version: 0.1.0
Author: Wouter Coppieters
Author URI: rorohiko.com
License: GPL2
*/
global $wpdb;
 header('Content-Type: text/html; charset=ASCII');
add_action('admin_menu', 'atomic_menu');


/**
Adds the Atomic Menu and the three sub-menus
*/
function atomic_menu() {
	
  add_menu_page("Atomic Settings","Atomic","manage_options","nuzbot-settings","atomic_settings","",61);
  add_submenu_page('nuzbot-settings','Layout Settings', 'Layout', 'manage_options', 'layout-settings', 'layout_settings');
  add_submenu_page('nuzbot-settings','Category Hopper Settings', 'Category Hopper', 'manage_options', 'category-hopper-settings', 'category_hopper_settings');
  add_submenu_page('nuzbot-settings','Developer Settings', 'Developer', 'manage_options', 'developer-settings', 'developer_settings');
  add_submenu_page('nuzbot-settings','Header and Sidebar', 'Header and Sidebar', 'manage_options', 'bar-settings', 'bar_settings');
 // add_submenu_page( 'options-general.php', 'Periodico Settings', 'Periodico Settings','manage_options', 'periodico-settings', 'periodico_settings');
}


/**
Allows users to determine layout/positioning and coloring of the website.
*/
function layout_settings() {
       global $wpdb;
       //Ensure the user has permission to access these settings.
       if (!current_user_can('manage_options')) {
         wp_die( __('You do not have sufficient permissions to access this page.') );
       }
       //Open and parse the layoutconfig.php file.
       $layoutConfigFileName = (dirname(__FILE__)."/../../themes/periodico/layoutconfig.php");
       $layoutConfig = fopen($layoutConfigFileName,'r');
       $variables = parseLayoutFile($layoutConfig);
       $layoutConfig = file($layoutConfigFileName);
       $hidden_field_name = 'mt_submit_hidden';

       // See if the user has posted us some information
       // If they did, this hidden field will be set to 'Y' and Settings will be updated.
       if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
            $layoutConfig = layout_settings_post($_POST,$variables,$layoutConfig);

         //Find the path to layout config
           $destinationPath = dirname(__FILE__)."/../../themes/periodico/layoutconfig.php";
           //Create a new layoutconfig
           $tempFile = fopen($destinationPath,'w+');

         if($tempFile == false) {
           echo "Unable to create file!";
         }
         //Write new data to the config file.
         foreach($layoutConfig as $line) {
           fwrite ($tempFile,$line);
         }
         fclose($tempFile);

       //Reload the updated values for the form.
       $layoutConfig = fopen($layoutConfigFileName,'r');
       $variables = parseLayoutFile($layoutConfig);
         ?>
               <div class="updated"><p><strong><?php _e('settings saved.', 'atomic-settings' ); ?></strong></p></div>
         <?php
       }
       // settings form
          echo '<div class="wrap">';
       // header
               echo "<h2>" . __( 'Layout Config Settings', 'atomic-settings' ) . "</h2>";
                     echo "<form name=\"form1\" method=\"post\" action=\"\" enctype =\"multipart/form-data\">";
                                        //Make a form out of the data  parsed earlier.
                                        construct_layout_config_form($variables);
                              echo "<hr/>";
                                   echo "<p class=\"submit\">";
                                        ?>  <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" /> <?php
                                   echo "</p>";
                               echo "<input type=\"hidden\" name=\"".$hidden_field_name."\" value=\"Y\">";
                    echo "</form>";
          echo "</div>";
   }



   /**
    * Constructs the HTML form out of data from layoutconfig.php
    */
    function construct_layout_config_form($variables){
          echo " <table style =\"border:5 \" border =\"5\" cellspacing=\"15\" cellpadding=\"0\">";
          $listIdx = 1;
           foreach($variables as $name => $variable) {
               if($variable["type"]!= "expression") {
                    echo"<tr>";
                    echo "<td>".$listIdx++.") ".$name."</td>";
                    if($variable["type"] == "boolean") {
                           echo "<td><input type=\"checkbox\". name=\"".$name."\" ".(($variable["value"] == "true")?"checked </td>":"").(($variable["comment"]!= null)?"<td style=\"color:gray\"><i>".$variable["comment"]."<i/></td>":"");
                         }
                    else {
                              echo "<td><input type=\"text\" name=\"".$name."\" value=\"".$variable["value"]."\"</td>".(($variable["type"]=="int")?"<td style=\"color:green\">Numerical</td>":"<td style=\"color:blue\">Text</td>").(($variable["comment"]!= null)?"<td style=\"color:gray\"><i>".$variable["comment"]."<i/></td>":"");
                      if($variable["upload"]!= null) {
                             echo "<td>
                                              <input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000000000000\" />
                                                       <input name=\"Upload:".$variable["upload"]."\" type=\"file\" />
                                               <input type=\"submit\" value=\"Upload File\" />
                                        </td>";
                             }
                    }
                 echo "</tr>";
             }
      }
      echo "</table>";
}

/**
 *Updates the layout variables according to the POST data.
 */
 function layout_settings_post($_POST,$variables,$layoutConfig){
       foreach($_POST as $name => $value) {
          //Ignore meta-data fields.
      if($name == "Submit" || $name == "mt_submit_hidden" || $name=="MAX_FILE_SIZE") continue;
          //extract the variable
      $variable = $variables[$name];

      //Process any uploads
      if($variable["upload"] && $_FILES["Upload:".$variable["upload"]]['name'] != null) {
        $target = dirname(__FILE__)."/../..".$variable["upload"].basename($_FILES["Upload:".$variable["upload"]]['name']);
           if(move_uploaded_file($_FILES["Upload:".$variable["upload"]]['tmp_name'], $target)) {
          echo "The file ".  basename( $_FILES["Upload:".$variable["upload"]]['name']).
                  " has been uploaded";
        } else {
          echo "There was an error uploading the file, please try again!";
        }
        continue;
      }
      if($value == "on") {
        //A Checkbox. Booleans get dealt with later..
        continue;
      }
      else{
        //Ensure user has entered correct value type. If not will revert to the old value.
        if((checkUserValueType($value) != $variable["type"]) && $variable["type"]!= "string") {
          echo "Error for value \"".$name."\" : cannot enter a ".checkUserValueType($value)." where a ". $variable["type"]. " is expected!<br/>";
          continue;
        }
        //Check for whitespace.
        if(preg_match("/\s/",trim($value))) {
       //Do nothing
        }
        //If old value is != new value, update it!
        if($variable["value"] != $value) {
          $variable["value"] = $value;
          $variables[$name] = $variable;
        }
      }
    }
    //Deal with booleans.
    foreach($variables as $name => $variable) {
      if($variable["type"] != "boolean") continue;
      if($_POST[$name] == "on") {
        $variable["value"] = "true";
      }
      else {
        $variable["value"] = "false";
      }//Update the data array
      $variables[$name] = $variable;
    }
    foreach ($variables as $variable) {
      $name = $variable["name"];
      //Add quotation marks to strings.
      if($variable["type"] == "string") {
        $value = "\"".$variable["value"]."\"";
      }
      else $value = $variable["value"];
      //Find and replace occurences of the variable in layoutConfig
      if(preg_replace("/".$variable["name"]."\s*=\s*([^\s]*?)\s*;/", "/".$name." = ".$value.";$/", $layoutConfig)) {
        $layoutConfig = preg_replace("/\\".$variable["name"]."\s*=\s*([^\s]*?)\s*;/", $variable["name"]." = ".$value.";", $layoutConfig);
      }
    }
    return $layoutConfig;
  }


/**
 * Parses the layoungconfig.php file into a data structure.
 */
function parseLayoutFile($layoutConfig){
$fileLine =fgets($layoutConfig);
  while($fileLine) {

    if(preg_match("/(\\$([^=]*))\s*=\s*(.*?)\s*;\s*(\/\/Upload (.*))*(\/\/.*)*/",$fileLine,$matches)) {
      $variable = array();
      $fullname = trim($matches[1]);
      $name = trim($matches[2]);
      $value = $matches[3];
      $comment = substr($matches[6],2);
      $upload = $matches[5];
      $type = checkType($value);
      $value = preg_replace("/[\"|\']/","",$value);
      $variable["name"] = $fullname;
      $variable["value"] = $value;
      $variable["type"] = $type;
      $variable["comment"]=$comment;
      $variable["upload"]=$upload;
      $variables[$name] = $variable;

    }
    $fileLine = fgets($layoutConfig);
  }
	return $variables;
}


/**
 * This menu allows the user to allocate new schedules, create new categories and determine the minnimum occupancy for categories.
 */
function category_hopper_settings() {
    
                                    

  global $wpdb;
  global $rorohiko_categoryHopper;
  //Check if user has capabilities to view this menu.
  if (!current_user_can('manage_options')) {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }
  //Parse the Category Hopper Data.
  $CatHopData = get_category_hopper_data($rorohiko_categoryHopper);
  $hidden_field_name = 'mt_submit_hidden';
  // See if the user has posted us some information
  // If they did, this hidden field will be set to 'Y'
  if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
  //User has pressed the add category button
  if(isset($_POST["Add_Category"])){
     //Check if Category name is valid
  	if(!isset($_POST["NewCat"]) || $_POST["NewCat"] == "Add A Category/Schedule Or...." ||$_POST["NewCat"] == ""){
  		echo "<span style=\"color:red;\">Please enter a category name</span>";
  	}
  	else{
       //Create new category.
       $CatHopData = rorohiko_add_category($_POST,$CatHopData);
        unset($_POST["NewCat"]);
     }
   }
  //Schedule hasn't changed sine the last POST so a change must have been made. Update the data.
  if($_POST["Schedule"] == $_POST["LastSchedule"] || $_POST["Schedule"]== "default"){
  $CatHopData = rorohiko_update_category_data($_POST,$CatHopData);
  }
  //Write new Category hopper settings.
  rorohiko_save_category_hopper_settings($CatHopData);
?><div class="updated"><p><strong><?php _e('settings saved.', 'atomic-settings' ); ?></strong></p></div><?php
}

     //Make the category hopper form.
     echo '<div class="wrap">';
          echo "<h2>" . __( 'Category Hopper Settings', 'atomic-settings' ) . "</h2>";
          echo "<form name=\"Category Hopper Schedule\" method=\"post\" action=\"\">";

               rorohiko_construct_category_hopper_form($CatHopData);

               ?> <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y"/><?php
    
    echo"</form>";
    echo '</div>';
    echo '</div>';
}
/**
 * This Method saves the modified Category Hopper Data back into its own options.
 */
function rorohiko_save_category_hopper_settings($CatHopData){
     global $rorohiko_categoryHopper;

     //String to write options to.
     $optionString = "occupancy=";

     //Occupancy first
            foreach($CatHopData["Occupation"] as $occValue){
              $optionString .= $occValue["name"]."(".$occValue["value"]."),";
            }
            $optionString = substr($optionString,0,-1)."\n";

  //Handle schedule blocks.
  foreach($CatHopData["Prefs"] as $preferences){
    $optionString .= "\nschedule=inbox-".$preferences["name"]."\n";

    //From block
    if(isset($preferences["from"])){
         foreach($preferences["from"] as $from){
           $optionString .= "from ";
           foreach($from as $frm){
                  $optionString .= $frm.",";
                }
           $optionString = substr($optionString,0,-1)."\n";
         }
    }

    //After block
    if(isset($preferences["after"])){
         foreach($preferences["after"] as $after){
                $optionString .= "after ";
                foreach($after as $aftr){
                       $optionString .= $aftr.",";
                     }
                $optionString = substr($optionString,0,-1)."\n";
              }
         }
  }

  //Write to options.
  $options["schedule_script"]=$optionString;
            update_option(
                $rorohiko_categoryHopper->adminOptionsKey,
                $options
            );
}

/**
 * This Method checks to see if a specified category does not exist. If so creates it, with a matching inbox and archive.
 * It then also creates a default schedule for the category
 */
function rorohiko_add_category($_POST,$CatHopData){

     $slug = cleanCategoryName($_POST["NewCat"]);
      $newCatName = $_POST["NewCat"];

     if(!$newCatName){
               echo "<span style=\"color:red;\">".$_POST["NewCat"]."please limit category names to a-z,A-Z,0-9 and simple symbols</span>";
            return $CatHopData;
       }
     foreach($CatHopData["Prefs"] as $prefs){
       if($prefs["name"] == $newCatName){
            echo "<span style=\"color:red;\">".$_POST["NewCat"]." already exists</span>";
            return $CatHopData;
       }
     }
     if(get_category_by_slug($slug)){
             echo "<span style=\"color:red;\">".$slug." :category with this slug already exists, please choose another name</span>";
            return $CatHopData;
     }
      $catarr = Array(
      'category_nicename' => $slug."-archive",
  		'cat_name' => $newCatName." Archived",
      'category_parent' => get_cat_ID("Archived")
  		);
  		wp_insert_category($catarr);
  		$catarr = Array(
           'category_nicename' => "inbox-".$slug,
  		'cat_name' => "Inbox ".$newCatName,
  		'category_description' => 'Active'
  		);
  		wp_insert_category($catarr);
  		$catarr = Array(
           'category_nicename' => $slug,
  		'cat_name' =>$newCatName,
  		'category_description' => 'leftsection#2000'
  		);
  		$cat = get_category(wp_insert_category($catarr));


  		$_POST["Schedule"] = $slug;
       $newPrefsBox["name"] = $slug;
        $newPrefsBox["from"][0][0] = "publish";
      $newPrefsBox["from"][0][1] = $slug;
      $newPrefsBox["after"][0][0] = "1 week";
      $newPrefsBox["after"][0][1] = $slug."-archive";
    array_push($CatHopData["Prefs"],$newPrefsBox);
    return $CatHopData;
}
function rorohiko_update_category_data($input,$data){
      foreach($data["Occupation"] as $oIdx => $occValue){
          foreach($input as $key => $value){
             if (substr($key,0,3) == "occ"){
              if ($occValue["name"] == substr($key,3)){
                    $occValue["value"] = $value;
            }
          }
        }
        $data["Occupation"][$oIdx] = $occValue;
      }
      if($_POST["Add_Occupancy"] == "+"){
        if($_POST["OccName"] != "Select new occupancy"){
          $newOccupancy["name"] = $_POST["OccName"];
          $newOccupancy["value"] = 10;
          $data["Occupation"][$oIdx+1] = $newOccupancy;
        }
        else{
          echo "<span style=\"color:red;\">You need to select an Occupancy Name</span>";
        }
      }else if($_POST["Subtract_Occupancy"] == "-"){
        unset($data["Occupation"][$oIdx]);
      }
      if($input["Schedule"] != "default"){
        foreach($data["Prefs"] as $prefsIdx => $prefs){
          if(trim($prefs["name"]) == $input["Schedule"]){
          	if(isset($_POST["Add_From_Rule"])){
          	$fromArray[0] = "publish";
          	$fromArray[1] = "archive";
          	if(!isset($prefs["from"]))$prefs["from"] = array();
          	array_push($prefs["from"],$fromArray);
          	$data["Prefs"][$prefsIdx] = $prefs;
          	}
          	if(isset($_POST["Add_After_Rule"])){
          	$fromArray[0] = "1 day";
          	$fromArray[1] = "archive";
          	if(!isset($prefs["after"])) $prefs["after"] = array();
          	array_push($prefs["after"],$fromArray);
          	$data["Prefs"][$prefsIdx] = $prefs;
          	}
          
            foreach($input as $inputKey => $inputValue){
              if($inputKey == "mt_submit_hidden" || substr($inputKey,0,3) == "occ") continue;
              if(preg_match("/from-([0-9]*)$/",$inputKey,$matches)){
                $fromIdx = $matches[1];
                $prefs["from"][$fromIdx][0] = $inputValue;
                $data["Prefs"][$prefsIdx] = $prefs;
              }
              if(preg_match("/from-place-([0-9]*)-([0-9]*)$/",$inputKey,$matches)){
                $fromIdx = $matches[1];
                $valIdx = $matches[2];
                $prefs["from"][$fromIdx][$valIdx+1] = $inputValue;
                $data["Prefs"][$prefsIdx] = $prefs;
              }
              if(preg_match("/after-val-([0-9]*)$/",$inputKey,$matches)){
                $afterIdx = $matches[1];
                $afterNum = $input["after-num-".$afterIdx];
                $prefs["after"][$afterIdx][0] = $afterNum." ".$inputValue;
                $data["Prefs"][$prefsIdx] = $prefs;
              }
              if(preg_match("/after-place-([0-9]*)-([0-9]*)$/",$inputKey,$matches)){
                $afterIdx = $matches[1];
                $valIdx = $matches[2];
                $afterNum = $input["after-num-".$afterIdx];
                $prefs["after"][$afterIdx][$valIdx+1] = $inputValue;
                $data["Prefs"][$prefsIdx] = $prefs;
              }
            }
            if(isset($_POST["Subtract_From_Rule"])){
          	unset($prefs["from"][count($prefs["from"])-1]);
          	$data["Prefs"][$prefsIdx] = $prefs;
          	}
          	 if(isset($_POST["Subtract_After_Rule"])){
          	unset($prefs["after"][count($prefs["after"])-1]);
          	$data["Prefs"][$prefsIdx] = $prefs;
          	}
          }
        }
      }

      return $data;
}
/**
 *Cleans a category name so it is web friendly, and simple to parse.
 * Replaces accented characters with their base counterparts and symbols with underscores
 * @param <type> $catName 
 */
function cleanCategoryName($catName){
     $catName = strip_tags($catName);

     if(mb_detect_encoding($catName) && mb_detect_encoding($catName) != "UTF-8"){
          $catName = mb_convert_encoding($catName, "UTF-8", mb_detect_encoding($catName));
     }

     $catName = utf8_decode($catName);
     for($i = 0; $i<strlen($catName); $i++){
          $char = $catName{$i};
          $asciiValue = ord($char);
          if($asciiValue == 96){
               $result .= "\'";
          }
          else if($asciiValue == 249 || $asciiValue == 250 || $asciiValue == 251 || $asciiValue == 252){
               $result .= "u";
          }
          else if($asciiValue == 232 || $asciiValue == 233 || $asciiValue ==234 || $asciiValue == 235){
               $result .= "e";
          }
          else if($asciiValue == 224 || $asciiValue == 225 || $asciiValue ==226 || $asciiValue == 227 || $asciiValue == 228 || $asciiValue == 229){
               $result .= "a";
          }
          else if($asciiValue == 236 || $asciiValue == 237 || $asciiValue ==238 || $asciiValue == 239){
               $result .= "i";
          }
          else if($asciiValue ==242 || $asciiValue == 243 || $asciiValue ==244  || $asciiValue ==246  || $asciiValue ==248 ){
               $result .= "o";
          }
          else if(!($asciiValue >47 && $asciiValue < 58) && !($asciiValue >64 && $asciiValue < 91) && !($asciiValue >96 && $asciiValue < 123)){
               $result .= "_";
              
          }
          else {
               $result .= strtolower($char);
          }
     }
     if(!$result) return false;
     else return $result;
}
function printPost($post){
  foreach($post as $key => $value){
    echo $key." => ".$value."<br/>";
  }
}
function developer_settings() {
  global $wpdb;
  global $rorohiko_categoryHopper;

  echo $adminOptions;
  if (!current_user_can('manage_options')) {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

  $CatHopConfigFileName = (dirname(__FILE__)."/../categoryhopper/categoryhopperconfig.php");
  $CatHopConfig = fopen($CatHopConfigFileName,'r');
  $variables = array();
  $fileLine =fgets($CatHopConfig);

  while($fileLine) {
    if(preg_match("/(\\$([^=]*))\s*=\s*([^\s]*)\s*?;/",$fileLine,$matches)) {
      $variable = array();
      $fullname = trim($matches[1]);
      $name = trim($matches[2]);
      $value = $matches[3];
      $type = checkType($value);
      $value = preg_replace("/[\"|\']/","",$value);
      $variable["name"] = $fullname;
      $variable["value"] = $value;
      $variable["type"] = $type;
      $variables[$name] = $variable;
    }

    $fileLine = fgets($CatHopConfig);
  }

  $CatHopConfig = file($CatHopConfigFileName);

  $hidden_field_name = 'mt_submit_hidden';

  // See if the user has posted us some information
  // If they did, this hidden field will be set to 'Y'
  if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
    foreach($_POST as $name => $value) {
      if($value == "on") {
        continue;
      }
      if($name == "Submit" || $name == "mt_submit_hidden") continue;
      $variable = $variables[$name];
      if((checkUserValueType($value) != $variable["type"]) && $variable["type"]!= "string") {
        echo "Error for value \"".$name."\" : cannot enter a ".checkUserValueType($value)." where a ". $variable["type"]. " is expected!<br/>";
        continue;
      }
 /**     if(preg_match("/\s/",trim($value))) {
        echo "Error at Entry:\"".$name."\" | Value :\"".$value."\". Entries cannot have spaces";
      }*/
      if($variable["value"] != $value) {
        $variable["value"] = $value;
        $variables[$name] = $variable;
      }
    }
    foreach($variables as $name => $variable) {
      if($variable["type"] != "boolean") continue;
      if($_POST[$name] == "on") {
        $variable["value"] = "true";
      }
      else {
        $variable["value"] = "false";
      }
      $variables[$name] = $variable;
    }
    foreach ($variables as $variable) {
      $name = $variable["name"];
      if($variable["type"] == "string") {
        $value = "\"".$variable["value"]."\"";
      }
      else $value = $variable["value"];
      if(preg_replace("/".$variable["name"]."\s*=\s*([^\s]*?)\s*;/", "/".$name." = ".$value.";/", $CatHopConfig)) {
        $CatHopConfig = preg_replace("/\\".$variable["name"]."\s*=\s*([^\s]*?)\s*;/", $name." = ".$value.";", $CatHopConfig);
      }
    }

    $destinationPath = dirname(__FILE__)."/../categoryhopper/categoryhopperconfig.php";


    $destination = fopen($destinationPath,'w+');
    if($destination == false) {
      echo "Unable to create file!";
    }
    foreach($CatHopConfig as $line) {
      fwrite ($destination,$line);
    }

    fclose($destination);
    ?>

<div class="updated"><p><strong><?php _e('settings saved.', 'atomic-settings' ); ?></strong></p></div>
    <?php
  }

  echo '<div class="wrap">';
  // header

  echo "<h2>" . __( 'Category Hopper Settings', 'atomic-settings' ) . "</h2>";






  ?>
<form name="Category Hopper Schedule" method="post" action="">

</form>
<form name="form1" method="post" action="">
  <table style ="border:5 " border ="5" cellspacing="15" cellpadding="0">
      <?php
      $listIdx = 1;
      foreach($variables as $name => $variable) {
        if($variable["type"]!= "expression") {
          echo"<tr>";
          echo "<td>".$listIdx++.") ".$name."</td><td>";
          if($variable["type"] == "boolean") {
            echo "<input type=\"checkbox\". name=\"".$name."\" ".(($variable["value"] == "true")?"checked </td><td style=\"color:green\">".$variable["type"]."</td>":"</td><td style=\"color:red\">".$variable["type"]."</td>");
          }
          else {
            echo "<input type=\"text\" name=\"".$name."\" value=\"".$variable["value"]."\"</td>".(($variable["type"]=="int")?"<td style=\"color:orange\">".$variable["type"]."</td>":"<td style=\"color:blue\">".$variable["type"]."</td>").(($variable["comment"]!= null)?"<td style=\"color:gray\"><i>".$variable["comment"]."<i/></td>":"");
          }
          echo "</tr>";
        }
      }
  ?>
  </table>
  <hr />

  <p class="submit">
    <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
  </p>
  <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
</form>
  <?php
  echo "</div>";
}

function atomic_settings() {
  global $wpdb;
  if (!current_user_can('manage_options')) {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

  // variables for the field and option names
  $opt_name = 'mt_favorite_color';
  $hidden_field_name = 'mt_submit_hidden';
  $data_field_name = 'mt_favorite_color';

  // Read in existing option value from database
  $opt_val = get_option( $opt_name );

  //Select all describable categories.


  // See if the user has posted us some information
  // If they did, this hidden field will be set to 'Y'
  if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
    $values = readValues();
    $arg = array('hide_empty' => false);
    $categories = get_categories($arg);
    foreach($categories as $name => $category) {
      if(preg_match("/lead|. archive|inbox/is", $category->name)) {
        unset($categories[$name]);
      }
    }
    $categores = array_values($categories);
    foreach($values as $value) {

      $database_taxonomies = $wpdb->get_results( "SELECT * FROM `wp_term_taxonomy` WHERE description != \"Active\" AND description != \"\"");
      $database_entries = Array();
      foreach($database_taxonomies as $entry) {
        $database_cat = $wpdb->get_results( "SELECT * FROM `wp_terms` WHERE term_id = \"".$entry->term_id."\"");
        $database_entries[$database_cat[0]->name] = $entry;
      }
      foreach($categories as $category) {
        $name = str_replace("_"," ", $category->name);
        $selectedCat = $database_entries[$name];

        $key = $value["value"].":".$category->name;
        $key = str_replace(" ", "_", $key);
        if(!$_POST[$key]) {
          if(!strpos(" ".$selectedCat->description,$value["value"])) {
            ##!echo "Value Already not set, Continuing<br/>";
            continue;
          }
          else {
            // echo "The Old Description for ".$category->name." is ".$selectedCat->description."<br/>";
            $newDescription =  $selectedCat->description;
            //  echo "Looking for ".$value["value"]."<br/>";
            $newDescription = trim(preg_replace("/".$value["value"].".*?(,|$)/", "", $newDescription));
            if(substr($newDescription,-1)==",") {
              $newDescription = substr($newDescription,0,-1);
            }
            $wpdb->update('wp_term_taxonomy',array('description' => $newDescription),array('term_id' => $selectedCat->term_id));
            //  echo "The New Description for ".$category->name." is ".$newDescription."<br/>";
            ##  echo "Unsetting ".$value["value"]." for ".$category->name."<br/>";
          }
        }
        else {
          if(strpos(" ".$selectedCat->description,$value["value"])) {
            ##!echo "Value Already Set, Continuing";
            continue;
          }
          else {
            ## echo "Setting ".$value["value"]." for ".$category->name;
            $newDescription = trim($selectedCat->description).", ".$value["value"];
            $newDescription = str_replace(",,", ",", $newDescription);
            $wpdb->update('wp_term_taxonomy',array('description' => $newDescription),array('term_id' => $selectedCat->term_id));
            $wpdb->flush();
          }

        }
      }
    }
    // Read their posted value
    $opt_val = $_POST[ $data_field_name ];

    // Save the posted value in the database
    update_option( $opt_name, $opt_val );

    // Put an settings updated message on the screen

    ?>
<div class="updated"><p><strong><?php _e('settings saved.', 'atomic-settings' ); ?></strong></p></div>
    <?php

  }

  // Now display the settings editing screen


  echo '<div class="wrap">';
  // header

  echo "<h2>" . __( 'Atomic Theme Category Settings', 'atomic-settings' ) . "</h2>";

  // settings form

  ?>
<form name="form1" method="post" action="">
  <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
  <table style ="border:5 " border ="5" cellspacing="15" cellpadding="0">
      <?php $values = readValues();
      $arg = array('hide_empty' => false);
      $categories = get_categories($arg);
      foreach($categories as $name => $category) {
        if(preg_match("/lead|. archive|inbox/is", $category->name)) {
          unset($categories[$name]);
        }
      }
      $categories = array_values($categories);
      $columns = count($categories);
      $rows = count($values);
      echo "<tr><td></td>";
      $idx = 0;
      foreach ($values as $value) {
        echo "<td>".$value["name"]."</td>";
        $orderedValues[$idx++] = $value;
      }
      foreach ($categories as $category) {
        echo "<tr>";
        echo "<td>".$category->name."</td>";
        for ($i = 0;$i<$rows;$i++) {
          $value = $orderedValues[$i];
          echo "<td><input type=\"checkbox\" name=\"".$value["value"].":".$category->name."\"".isChecked($value["value"],$category->name)."</td>";
        }
      }
      echo '<div/>';
  ?>
  </table>

  <hr />

  <p class="submit">
    <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
  </p>

</form>




  <?php }
function checkUserValueType($value) {
  if(!preg_match("/[^0-9-]/",$value)) {
    $type = "int";
  }
  else {
    $type = "string";
  }
  return $type;
}
function checkType($value) {
  if(strtolower($value) == "true" ||strtolower($value) == "false") {
    $type = "boolean";
  }
  elseif(!preg_match("/[\"|\'].*?[\"|\']/",$value)) {
    if(preg_match("/.*?[\s|+|\*|$].*/",$value)) {
      $type = "expression";
    }
    else {
      $type = "int";
    }
  }

  else {
    $type = "string";

  }
  return $type;
}

function readValues() {
  $ValuesFileName = dirname(__FILE__)."/Values";

  if(!file_exists($ValuesFileName)){
       $ValuesFile = fopen($ValuesFileName,'w+');
       fwrite($ValuesFile, "AllowVideo:Allows Video
AllowComments:Allows Comments
NoLead:Has No Lead
SuppressWhatsHappening: Suppress Whats Happening
SuppressPopularStories: Suppress Popular Stories
NarrowMiddleLayout: Narrow Middle Layout
ShowFullStory: Don't compress stories");
           fclose($ValuesFile);
       }
$ValuesFile = fopen($ValuesFileName,'r');
  
  $value = fgets($ValuesFile);
  while($value) {
    preg_match("/(.*):(.*)/", $value, $matches);
    $variable = trim($matches[1]);
    $name = trim($matches[2]);
    $CategoryItem["name"] = $name;
    $CategoryItem["value"] =$variable;
    $values[$i++] = $CategoryItem;
    $value = fgets($ValuesFile);
  }
  return $values;
}
function isChecked($value,$name) {
  global $wpdb;
  $database_entries = get_description_entries();
  $selectedCat = $database_entries[$name];
  if(strpos(" ".$selectedCat->description,$value)) {
    return "checked";
  }
}
function get_description_entries() {
  global $wpdb;
  $database_taxonomies = $wpdb->get_results( "SELECT * FROM `wp_term_taxonomy` WHERE description != \"Active\" AND description != \"\"");
  $database_entries = Array();
  foreach($database_taxonomies as $entry) {
    $database_cat = $wpdb->get_results( "SELECT * FROM `wp_terms` WHERE term_id = \"".$entry->term_id."\"");
    $database_entries[$database_cat[0]->name] = $entry;
  }
  return $database_entries;
}

function get_category_hopper_data($rorohiko_categoryHopper) {
  $adminOptions = $rorohiko_categoryHopper->getAdminOptions();
  $adminOptions = $adminOptions["schedule_script"];
  preg_match("/occupancy=(.*)/",$adminOptions,$matches);
  $occValuesTemp = explode(",",$matches[1]);
  foreach($occValuesTemp as $occValue) {
    preg_match("/(.*?)\(([0-9]*)/",$occValue,$matches);
    $OccupationValues[$i]["name"] = trim($matches[1]);
    $OccupationValues[$i++]["value"] = $matches[2];
  }
  $lines = explode("\n",$adminOptions);
  for($i = 2;$i<count($lines);$i++) {
    if(preg_match("/schedule=inbox-(.*)/",$lines[$i],$matches)) {
      if($currentSchedule) {
        $preferences[$schedIdx++] = $currentSchedule;
        $currentSchedule = array();
      }
      $fromIdx = 0;
      $afterIdx = 0;
      $currentSchedule["name"]= trim($matches[1]);
      //echo $currentSchedule["name"];
    }
    while(!$currentSchedule["name"]) {
      continue;
    }
    unset($matches);
    $matches = array();
    if (preg_match("/from\s(.*)/",$lines[$i],$matches)) {
      $currentSchedule["from"][$fromIdx++] = explode(",",$matches[1]);
    }
    unset($matches);
    $matches = array();
    if (preg_match("/after\s(.*)/",$lines[$i],$matches)) {
      $currentSchedule["after"][$afterIdx++] = explode(",",$matches[1]);
    }
  }
  $preferences[$schedIdx++] = $currentSchedule;
  if(false) {
    foreach($preferences as $prefs) {
      echo "Preferences block :<br/>";
      echo "Name is :".$prefs["name"]."<br/>";
      foreach ($prefs["from"] as $fromBlock) {
        foreach($fromBlock as $from) {
          echo "From :".$from."<br/>";
        }
      }
      $fromBlock = array();
      $from = array();
      foreach ($prefs["after"] as $afterBlock) {
        foreach($afterBlock as $after) {
          echo "After :".$after."<br/>";
        }
        unset($after);
        $after = array();
      }
      unset($afterBlock);
      $afterBlock = array();
    }
  }
  $details["Occupation"] = $OccupationValues;
  $details["Prefs"]= $preferences;

  return $details;
}
function rorohiko_construct_category_hopper_form($details) {
  global $loadFromFile;
  ?>


<hr />

  <?php
  $preferences = $details["Prefs"];
  ?>

<br/>
  <table>
  <tr><td><h2>Schedules:</h2></td></tr>
  <tr><td><br/></td></tr>
  <tr><td>Add Category:</td><td><input type="text" name="NewCat" value="<?php if(!$_POST) echo"Add A Category/Schedule Or....";?>" onclick="this.value='';" size="35"/><input type="submit" name="Add Category" class="button-primary" value="<?php esc_attr_e('+') ?>" /></td></tr>
  <tr><td><br/></td></tr>
  <tr><td>Modify Schedule:</td><td>
      <input type="hidden" name="LastSchedule" value="<?php
        if ($_POST['Schedule'] != $_POST['LastSchedule']) {
          $loadFromFile = true;
        }
        else {
          $loadFromFile = false;
               }
  echo $_POST["Schedule"];?>" />
      <select name="Schedule" onchange ="this.form.submit();">
        <option value="default">Modify an existing schedule</option>
          <?php


          foreach($preferences as $schedule) {
    ?>
        <option <?php if(trim($_POST['Schedule']) == trim($schedule["name"])) print 'selected';?> value="<?php echo $schedule["name"]?>"><?php echo $schedule["name"]?></option>

            <?php
          }
  ?>
      </select>
    </td></tr>
    <?php

    global $fromIdx;
    global $afterIdx;
    global $placeIdx;
    $placeIdx = 0;
    $fromIdx = 0;
    $afterIdx = 0;
    unset($schedule);
    foreach($preferences as $schedule) {
      ?>
      <?php if(trim($_POST['Schedule']) == trim($schedule["name"])) {
      if(isset($schedule["from"])){
        foreach($schedule["from"] as $fromBlock) {?>
  <tr><td><h3>From:</h3></td><td><?php rorohiko_get_times(true,$fromBlock[0]); ?> </td>
        <? for($i = 1; $i<count($fromBlock); $i++) { ?>
    <td><?php rorohiko_get_place($fromBlock[$i],$fromIdx,"from-") ?> </td>
              <?php
              $placeIdx++;
        }
        unset($fromBlock);
        $fromBlock = array();
        $placeIdx = 0;?>
    <!--<td><input type="submit" name="Add From" class="button-primary" value="<?php esc_attr_e('+') ?>" /><input type="submit" name="Remove From" class="button-primary" value="<?php esc_attr_e('-') ?>" /></td>-->
  </tr>
          <?php
          $fromIdx++;
        }
      }?>
        <tr><td><input type="submit" name="Add From Rule" class="button-primary" value="<?php esc_attr_e('+') ?>" /><input type="submit" name="Subtract From Rule" class="button-primary" value="<?php esc_attr_e('-') ?>" /></td></tr>
      <?php if(isset($schedule["after"])){
        foreach($schedule["after"] as $afterBlock) {?>
  <tr><td><h3>After:</h3></td><td><?php rorohiko_get_times(false,$afterBlock[0]); ?> </td>
        <? for($i = 1; $i<count($afterBlock); $i++) { ?>
    <td><?php rorohiko_get_place($afterBlock[$i],$afterIdx,"after-") ?> </td>
              <?php
              $placeIdx++;
        }
        $placeIdx = 0;?>
    <!--<td><input type="submit" name="Add After" class="button-primary" value="<?php esc_attr_e('+') ?>" /><input type="submit" name="Remove After" class="button-primary" value="<?php esc_attr_e('-') ?>" /></td>-->
  </tr>
          <?php
          $afterIdx++;
      }
      }?>
  <td><input type="submit" name="Add After Rule" class="button-primary" value="<?php esc_attr_e('+') ?>" /><input type="submit" name="Subtract After Rule" class="button-primary" value="<?php esc_attr_e('-') ?>" /></td>
        <?php }
} ?>
</table>
<table style ="border:5 " border ="5" cellspacing="15" cellpadding="0">
  <tr><td><h2>Occupancy:</h2></td></tr>
      <?php
      $usedValues = array();
      foreach ($details["Occupation"] as $OccValue) {
        array_push($usedValues, $OccValue["name"]);
        echo "<tr><td>".$OccValue["name"]."</td><td><select name=\"occ".$OccValue["name"]."\" onchange =\"this.form.submit();\">";
        for($i = 1; $i<11;$i++) {
          echo "<option value=\"".$i."\"".(($OccValue["value"]==$i)?"selected=\"selected\"":"").">".$i."</option>";
        }
        echo "</select></td></tr>";
      }
  ?>
   <tr><td><select name="OccName">
       <option value="Select new occupancy">Select new occupancy</option>

 <?php

 $args = array(
          'hide_empty' => 0
  );
    $categories = get_categories($args);
foreach($categories as $name => $category) {
      if(preg_match("/lead|. archive|inbox/is", $category->name)) {
        unset($categories[$name]);
      }
    }
   foreach($categories as $category) {
     if(!in_array($category->slug, $usedValues)){?>

  <option value=<?php echo $category->slug ?>><?php echo $category->slug ?></option>
    <?php } }?>
</select></td><td><input type="submit" name="Add Occupancy" class="button-primary" value="<?php esc_attr_e('+') ?>" /><input type="submit" name="Subtract Occupancy" class="button-primary" value="<?php esc_attr_e('-') ?>" /></td>
  </tr>
</table>

  <?php
}
function rorohiko_get_times($isFrom,$Value) {
  global $fromIdx;
  global $afterIdx;
  global $loadFromFile;

  if($isFrom) {
    if($loadFromFile) {
      $_POST['from-'.$fromIdx] = trim($Value);
    } ?>
<select name="from-<?php echo $fromIdx ?>" onchange ="this.form.submit();">
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "publish") print 'selected' ?> value="publish">Publish</option>
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "monday") print 'selected' ?> value="monday">Monday</option>
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "tuesday") print 'selected' ?> value="tuesday">Tuesday</option>
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "wednesday") print 'selected' ?> value="wednesday">Wednesday</option>
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "thursday") print 'selected' ?> value="thursday">Thursday</option>
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "friday") print 'selected' ?> value="friday">Friday</option>
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "saturday") print 'selected' ?> value="saturday">Saturday</option>
  <option <?php  if(trim($_POST['from-'.$fromIdx]) == "sunday") print 'selected' ?> value="sunday">Sunday</option>
</select>
    <?}
  else {
    if($loadFromFile) {
      preg_match("/([0-9]*)\s([^\s]*)/",$Value,$matches);
      $_POST['after-val-'.$afterIdx] = $matches[2];
      $_POST['after-num-'.$afterIdx] = $matches[1];
    }
    if (!(trim($_POST['after-val-'.$afterIdx]))) {
      $_POST['after-val-'.$afterIdx] = "hours";
    }
    if(trim($_POST['after-val-'.$afterIdx]) == "hours" || trim($_POST['after-val-'.$afterIdx]) == "hour") $countTo = 48;
    else if(trim($_POST['after-val-'.$afterIdx]) == "days" || trim($_POST['after-val-'.$afterIdx]) == "day") $countTo = 30;
      else if(trim($_POST['after-val-'.$afterIdx]) == "weeks" || trim($_POST['after-val-'.$afterIdx]) == "week") $countTo = 10;
    else if(trim($_POST['after-val-'.$afterIdx]) == "months" || trim($_POST['after-val-'.$afterIdx]) == "month") $countTo = 24;?>
<select name="after-num-<?php echo $afterIdx ?>" onchange ="this.form.submit();">
    <?php for($i = 1; $i<=$countTo ; $i++) { ?>
  <option <?php  if(trim($_POST['after-num-'.$afterIdx]) == $i) print 'selected' ?> value="<?php echo $i ?>"><?php echo $i ?></option>
      <?php } ?>
</select>
<select name="after-val-<?php echo $afterIdx ?>" onchange ="this.form.submit();">

  <option <?php  if(trim($_POST['after-val-'.$afterIdx]) == "hours" || trim($_POST['after-val-'.$afterIdx]) == "hour") print 'selected' ?> value="hours">Hours</option>
  <option <?php  if(trim($_POST['after-val-'.$afterIdx]) == "days" || trim($_POST['after-val-'.$afterIdx]) == "day") print 'selected' ?> value="days">Days</option>
  <option <?php  if(trim($_POST['after-val-'.$afterIdx]) == "weeks" || trim($_POST['after-val-'.$afterIdx]) == "week") print 'selected' ?> value="weeks">Weeks</option>
  <option <?php  if(trim($_POST['after-val-'.$afterIdx]) == "months" || trim($_POST['after-val-'.$afterIdx]) == "month") print 'selected' ?> value="months">Months</option>
</select>
    <?php


  }
}
function rorohiko_get_place($Value,$idx,$identifier) {
  global $placeIdx;
  global $loadFromFile;

  if($loadFromFile) {
    $_POST[$identifier.'place-'.$idx."-". $placeIdx] = trim($Value);
  }
  $args = array(
          'hide_empty' => 0
  );
    $categories = get_categories($args);
  ?>
<select name="<?php echo $identifier."place-".$idx."-".$placeIdx;?>" onchange ="this.form.submit();">
  <?php foreach($categories as $category) { ?>
  <option <?php  if(trim($_POST[$identifier.'place-'.$idx."-". $placeIdx]) == $category->slug) print 'selected' ?> value=<?php echo $category->slug ?>><?php echo $category->name ?></option>
    <?php } ?>
</select>
  <?
}
function bar_settings() {
global $wpdb;
$hidden_field_name = 'mt_submit_hidden';
    $arg = array('hide_empty' => false);
    $categories = get_categories($arg);
    
    foreach($categories as $name => $category) {
      if(preg_match("/lead|. archive|inbox/is", $category->name)) {
        unset($categories[$name]);
      }
    }
    $data = parse_category_positions($categories);
    if(isset($_POST)){
     foreach($_POST as $key => $value){
         $catName =  substr($key,3);
         if(substr($key,0,3) == "loc"){
             if($data[$catName] == "top"){
                 if($value == "Sidebar"){
                     $data[$catName] = "left";
                     $data["left"][$catName] = $data["top"][$catName];
                     unset($data["top"][$catName]);
                 }
             }
             else if($data[$catName] == "left"){
                 if($value == "Header"){
                     $data[$catName] = "top";
                     $data["top"][$catName] = $data["left"][$catName];
                     unset($data["left"][$catName]);
                 }
             }
             
         }
     }
     foreach($data["top"] as $key => $value){
         if(is_int((int)$_POST[$key]) && $_POST[$key] != ""){
             $data["top"][$key] = $_POST[$key];
             $id = (int)get_cat_ID($key);
             $id = get_category_by_slug($key)->cat_ID;
             $description = get_category($id)->description;
             $description = preg_replace("/(topsection#([0-9]*))|(leftsection#([0-9]*))/", "topsection#".$_POST[$key], $description);
             $wpdb->update('wp_term_taxonomy',array('description' => $description),array('term_id' => $id));
         }
     }
     foreach($data["left"] as $key => $value){
         if(is_int((int)$_POST[$key]) &&  $_POST[$key] != ""){
             $data["left"][$key] = $_POST[$key];
             $id = get_category_by_slug($key)->cat_ID;
             $description = get_category($id)->description;
             $description = preg_replace("/(topsection#([0-9]*))|(leftsection#([0-9]*))/", "leftsection#".$_POST[$key], $description);
              $wpdb->update('wp_term_taxonomy',array('description' => $description),array('term_id' => $id));
         }
     }
    asort($data["top"]);
    asort($data["left"]);
    }
    ?>
<table>
<form method="post" action="">
    <?php 
    echo "<td><h2>Header</h2></td>";
    foreach($data["top"] as $name => $value){
        echo "<tr><td>".$name."</td><td><input type=\"text\" name=\"".$name."\" value=\"".$value."\"onblur=\"this.form.submit();\"/></td><td><select name=\"loc".$name."\" onchange=\"this.form.submit();\"><option>Header</option><option>Sidebar</option></select></td></tr>";
    }
    echo "<td><h2>Sidebar</h2></td>";
    foreach($data["left"] as $name => $value){
        echo "<tr><td>".$name."</td><td><input type=\"text\" name=\"".$name."\" value=\"".$value."\" onblur=\"this.form.submit();\"/></td><td><select name=\"loc".$name."\" onchange=\"this.form.submit();\"><option>Sidebar</option><option>Header</option></select></td></tr>";
    }
?>
    <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y"/>
</form>
</table>
<?php
}
function parse_category_positions($categories){
    $categories["top"] = array();
    $categories["left"] = array();
    foreach($categories as $category){
        if(preg_match("/topsection#([0-9]*)/", $category->description, $matches)){
            $categories["top"][$category->slug] = (int)trim($matches[1]);
            $categories[$category->slug] = "top";
        }
        else if(preg_match("/leftsection#([0-9]*)/", $category->description, $matches)){
            $categories["left"][$category->slug] = (int)trim($matches[1]);
            $categories[$category->slug] = "left";
        }
        unset($matches);
    }
    return $categories;
}
?>