add_filter('gform_validation', callback: function ($validation_result) {
    //get form
	$form = $validation_result['form'];
    //check is nested/child form.
	$maybe_nested_form =  new GP_Nested_Forms($form);
	$is_nested_form = $maybe_nested_form -> is_nested_form_submission();
    //if not child form do nothing
	if (!$is_nested_form){
		return $validation_result;
	}

    //check if field called amount
	foreach ($form['fields'] as $field){
        //if there is then validate
		if ($field -> label == 'Amount'){//change 'Amount' to the label on child forms. make sure its the same on each child form
            //get parent form then all child form entries
            $parent_id = $maybe_nested_form->get_parent_form_id();
            $parent_form = GFAPI::get_form($parent_id);
            $parent_form = new GP_Nested_Forms($parent_form);
            $session = new GPNF_Session($parent_id);
            $entry_id_array = $session->get_valid_entry_ids($session->get_cookie()['nested_entries']);
            $entries_array = array();
            foreach ($entry_id_array as $entryid){
                if (is_array($entryid)){
                    foreach ($entryid as $id){
                        $entries_array[] = $id;
                    }
                }
                else{
                    $entries_array[] = $entryid;
                }
            }
            $entries = $parent_form->get_entries($entries_array);
            //add the total
            $value = 0;
            foreach ($entries as $entry){
                $child_form_id = $entry['form_id'];
                $child_form = GFAPI::get_form($child_form_id);
                foreach ($child_form['fields'] as $child_field){
                    if ($child_field->label == 'Amount'){//change 'Amount' to the label on child forms. make sure its the same on each child form
                        $field_id = $child_field->id;
                        $value += intval($entry[$field_id]);
                    }
                }
            }
            $value += $field->gppa_hydrated_value;
            //check against total on parent form
            $parent_form = GFAPI::get_form($parent_id);
            foreach ($parent_form['fields'] as $parent_field){
                if ($parent_field -> label == 'Total'){//change 'Total' to label of field on parent form
                    $calc = new GP_Advanced_Calculations();
                    $formula = $parent_field->calculationFormula;
                    $total = $calc->eval_formula($formula);
                    if ($value > $total){
	                    $field -> failed_validation = true;
	                    $field -> validation_message = "The amount you entered exceeds your total."; //change message to whatever you want message to be
	                    $validation_result['is_valid'] = false;
                    }
	                $validation_result['form'] = $form;
	                return $validation_result;
                }
            }
		}
	}
	$validation_result['form'] = $form;
	return $validation_result;
});
