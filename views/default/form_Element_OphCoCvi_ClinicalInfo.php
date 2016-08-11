<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2016
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2016, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

?>

<div class="element-fields row">
    <?php echo $form->datePicker($element, 'examination_date', array('maxDate' => 'today'), array('style' => 'width: 110px;')) ?>
    <?php echo $form->radioBoolean($element, 'is_considered_blind') ?>
    <?php echo $form->radioBoolean($element, 'sight_varies_by_light_levels') ?>
    <?php echo $form->textField($element, 'unaided_right_va', array('size' => '10')) ?>
    <?php echo $form->textField($element, 'unaided_left_va', array('size' => '10')) ?>
    <?php echo $form->textField($element, 'best_corrected_right_va', array('size' => '10')) ?>
    <?php echo $form->textField($element, 'best_corrected_left_va', array('size' => '10')) ?>
    <?php echo $form->textField($element, 'best_corrected_binocular_va', array('size' => '10')) ?>
    <?php echo $form->dropDownList($element, 'low_vision_status_id',
        CHtml::listData(OEModule\OphCoCvi\models\OphCoCvi_ClinicalInfo_LowVisionStatus::model()->findAll(array('order' => 'display_order asc')),
            'id', 'name'), array('empty' => '- Please select -')) ?>
    <?php echo $form->dropDownList($element, 'field_of_vision_id',
        CHtml::listData(OEModule\OphCoCvi\models\OphCoCvi_ClinicalInfo_FieldOfVision::model()->findAll(array('order' => 'display_order asc')),
            'id', 'name'), array('empty' => '- Please select -')) ?>
    <?php echo $form->multiSelectList($element, 'MultiSelect_disorders', 'disorders', 'ophcocvi_clinicinfo_disorder_id',
        CHtml::listData(OEModule\OphCoCvi\models\OphCoCvi_ClinicalInfo_Disorder::model()->findAll(array('order' => 'display_order asc')),
            'id', 'name'), null, array('empty' => '- Please select -', 'label' => 'Disorders')) ?>
    <?php echo $form->textArea($element, 'diagnoses_not_covered', array('rows' => 6, 'cols' => 80)) ?>
    <?php echo $form->dropDownList($element, 'consultant_id',
        CHtml::listData(User::model()->findAll(array('order' => 'last_name asc')), 'id', 'last_name'),
        array('empty' => '- Please select -')) ?>
</div>
