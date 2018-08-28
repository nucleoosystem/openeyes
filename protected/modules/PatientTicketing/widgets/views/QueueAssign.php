<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2014
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2014, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * @var OEModule\PatientTicketing\models\Queue $queue
 */
if ($queue) { ?>
  <table class="cols-full">
      <?php
      foreach ($form_fields as $fld) {
          if (@$fld['type'] == 'widget') {
              $this->widget('OEModule\PatientTicketing\widgets\\' . $fld['widget_name'], array(
                  'ticket' => $this->ticket,
                  'label_width' => $this->label_width,
                  'data_width' => $this->data_width,
                  'form_name' => $fld['form_name'],
                  'form_data' => $form_data,
              ));
          } elseif (@$fld['choices']) { ?>
            <tr>
              <td>
                <label for="<?= $fld['form_name'] ?>"><?= $fld['label'] ?>:</label>
              </td>
              <td> <?= CHtml::dropDownList(
                      $fld['form_name'],
                      @$form_data[$fld['form_name']],
                      $fld['choices'],
                      array('empty' => ($fld['required']) ? ' - Please Select - ' : 'None'));
                  ?>
              </td>
            </tr>
          <?php } else {
              //may need to expand this beyond textarea and select in the future.
              $notes = @$form_data[$fld['form_name']];
              ?>
            <tr>
              <td colspan="2">
                <textarea
                    id="<?= $fld['form_name'] ?>"
                    name="<?= $fld['form_name'] ?>"
                    rows="1"
                    class="cols-full"><?= $notes ?></textarea>
              </td>
            </tr>
          <?php } ?>
      <?php } ?>

      <?php if ($auto_save) {
          ?>
        <script>
          $(document).ready(function () {
            window.patientTicketChanged = true;
            window.changedTickets[<?=$this->current_queue_id?>] = true;
          });
        </script>
          <?php

      }
      ?>
  </table>
    <?php if ($this->patient_id) { ?>
    <div class="vc-actions">
      <div class="row">
          <?php foreach ($queue->event_types as $et) { ?>
            <a href="<?= Yii::app()->baseURL ?>/<?= $et->class_name ?>/default/create?patient_id=<?= $this->patient_id ?>"
               class="button blue hint js-auto-save"
               data-queue="<?= $this->current_queue_id ?>"
            >
                <?= $et->name ?>
            </a>
          <?php } ?>

          <?php if ($print_letter_event) { ?>
            <a href="<?= Yii::app()->baseURL ?>/<?= $print_letter_event->eventType->class_name ?>/default/doPrintAndView/<?= $print_letter_event->id ?>?all=1"
               class="button blue hint js-auto-save"
               data-queue="<?= $this->current_queue_id ?>"
            >
              Print Letter
            </a>
          <?php } ?>
      </div>
        <?php echo @$extra_view_data['buttons']; ?>
    </div>
    <?php } ?>
<?php } ?>