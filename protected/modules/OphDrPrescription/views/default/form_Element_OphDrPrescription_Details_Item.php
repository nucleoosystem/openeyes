<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<tr data-key="<?php echo $key ?>" class="prescription-item prescriptionItem
  <?php if ($item->getErrors()): ?>errors<?php endif; ?>">
  <td>
      <button class="js-add-taper">
          <i class="oe-i child-arrow small"></i>
      </button>
  </td>
  <td>
      <?php if (isset($patient) && $patient->hasDrugAllergy($item->drug_id)): ?>
      <i class="oe-i warning small pad js-has-tooltip" data-tooltip-content="Allergic to <?= implode(',',$patient->getPatientDrugAllergy($item->drug_id))?>"></i>
      <?php endif; ?>
      <?php echo $item->drug->tallmanlabel; ?>
      <?php if ($item->id) { ?>
        <input type="hidden" name="prescription_item[<?php echo $key ?>][id]" value="<?php echo $item->id ?>" /><?php
      } ?>
    <input type="hidden" name="prescription_item[<?php echo $key ?>][drug_id]" value="<?php echo $item->drug_id ?>"/>
      <?php if($item->comments){ ?>
        <i class="oe-i comments-added active medium-icon pad js-add-comments js-has-tooltip" style="" data-tooltip-content="<?=\CHtml::encode($item->comments);?>"></i>
       <?php } else { ?>
        <i class="oe-i comments medium-icon pad js-add-comments"style=""></i>
       <?php } ?>

      <div id="comments-<?=$key?>" class="cols-full prescription-comments" style="display:none" data-key="<?php echo $key; ?>">
          <!-- comment-group, textarea + icon -->
          <div class="comment-group flex-layout flex-left" style="padding-top:5px">
              <?php
                $htmlOptions = [
                        'placeholder'=>'Comments' , 'autocomplete'=>'off',
                    'rows'=>'1', 'class'=>'js-input-comments cols-full ' ,
                    'style'=>'overflow-x: hidden;word-wrap: break-word;'
                ];
              echo CHtml::textArea('prescription_item[' . $key . '][comments]' ,CHtml::encode($item->comments) ,$htmlOptions) ?>
              <i class="oe-i remove-circle small-icon pad-left  js-remove-add-comments"></i>
          </div>
      </div>

  </td>
  <td class="prescriptionItemDose">
      <?=\CHtml::textField('prescription_item[' . $key . '][dose]', $item->dose,
          array('autocomplete' => Yii::app()->params['html_autocomplete'], 'class' => 'cols-11')) ?>
  </td>
  <td>
      <?=\CHtml::dropDownList('prescription_item[' . $key . '][route_id]', $item->route_id,
          CHtml::listData(DrugRoute::model()->activeOrPk($item->route_id)->findAll(array('order' => 'display_order asc')),
              'id', 'name'), array('empty' => '-- Select --', 'class' => 'drugRoute cols-11')); ?>
  </td>

    <?php if (!strpos(Yii::app()->controller->action->id, 'Admin')) { ?>
      <td class='route_option_cell'>

          <?php if ($item->route && $options = $item->route->options) {
              echo CHtml::dropDownList('prescription_item[' . $key . '][route_option_id]', $item->route_option_id,
                  CHtml::listData($options, 'id', 'name'), array('empty' => '-- Select --'));
          } else {
              echo '-';
          } ?>
      </td>
    <?php } ?>

  <td class="prescriptionItemFrequencyId">
      <?=\CHtml::dropDownList('prescription_item[' . $key . '][frequency_id]', $item->frequency_id,
          CHtml::listData(DrugFrequency::model()->activeOrPk($item->frequency_id)->findAll(array('order' => 'display_order asc')),
              'id', 'name'), array('empty' => '-- Select --', 'class' => 'cols-11')); ?>
  </td>
  <td class="prescriptionItemDurationId">
      <?=\CHtml::dropDownList('prescription_item[' . $key . '][duration_id]', $item->duration_id,
          CHtml::listData(DrugDuration::model()->activeOrPk($item->duration_id)->findAll(array('order' => 'display_order')),
              'id', 'name'), array('empty' => '-- Select --', 'class' => 'cols-11')) ?>
  </td>
  <td>
      <?=\CHtml::dropDownList('prescription_item[' . $key . '][dispense_condition_id]',
          $item->dispense_condition_id, CHtml::listData(OphDrPrescription_DispenseCondition::model()->findAll(array(
              'condition' => "active or id='" . $item->dispense_condition_id . "'",
              'order' => 'display_order',
          )), 'id', 'name'), array('class' => 'dispenseCondition cols-11', 'empty' => '-- Please select --')); ?>

  </td>
  <td>
      <?php
      $locations = $item->dispense_condition ? $item->dispense_condition->locations : array('');
      $style = $item->dispense_condition ? '' : 'display: none;';
      echo CHtml::dropDownList('prescription_item[' . $key . '][dispense_location_id]', $item->dispense_location_id,
          CHtml::listData($locations, 'id', 'name'), array('class' => 'dispenseLocation cols-11', 'style' => $style));
      ?>
  </td>
  <td>
    <i class="oe-i trash removeItem"></i>
  </td>
</tr>

<?php
$count = 0;
foreach ($item->tapers as $taper): ?>
  <tr data-key="<?php echo $key ?>" data-taper="<?php echo $count ?>"
      class="prescription-tapier <?php echo ($key % 2) ? 'odd' : 'even'; ?>">
    <td></td>
    <td>
      <i class="oe-i child-arrow small no-click pad"></i>
      <em class="fade">then</em>
        <?php if ($taper->id) { ?>
          <input type="hidden" name="prescription_item[<?php echo $key ?>][taper][<?php echo $count ?>][id]"
                 value="<?php echo $taper->id ?>"/>
        <?php } ?>
    </td>

    <td>
        <?=\CHtml::textField('prescription_item[' . $key . '][taper][' . $count . '][dose]', $taper->dose,
            array('autocomplete' => Yii::app()->params['html_autocomplete'], 'class' => 'cols-11')) ?>
    </td>
    <td></td>
      <?php if (!strpos(Yii::app()->controller->action->id, 'Admin')) { ?>
        <td></td>
      <?php } ?>
    <td>
        <?=\CHtml::dropDownList('prescription_item[' . $key . '][taper][' . $count . '][frequency_id]',
            $taper->frequency_id,
            CHtml::listData(DrugFrequency::model()->activeOrPk($taper->frequency_id)->findAll(array('order' => 'display_order asc')),
                'id', 'name'), array('empty' => '-- Select --', 'class' => 'cols-11')); ?>
    </td>
    <td>
        <?=\CHtml::dropDownList('prescription_item[' . $key . '][taper][' . $count . '][duration_id]',
            $taper->duration_id,
            CHtml::listData(DrugDuration::model()->activeOrPk($taper->duration_id)->findAll(array('order' => 'display_order asc')),
                'id', 'name'), array('empty' => '-- Select --', 'class' => 'cols-11')); ?>
    </td>
    <td></td>
    <td></td>
    <td class="prescription-actions">
      <i class="oe-i trash removeTaper"></i></td>
    </td>
  </tr>
    <?php
    ++$count;
endforeach; ?>
