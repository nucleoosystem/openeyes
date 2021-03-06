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

Yii::app()->clientScript->registerScriptFile("{$this->assetPath}/js/pages.js", \CClientScript::POS_HEAD);
Yii::app()->clientScript->registerScriptFile("{$this->assetPath}/js/imageLoader.js", \CClientScript::POS_HEAD);
$correspondeceApp = Yii::app()->params['ask_correspondence_approval']; ?>
<div class="element-data full-width flex-layout flex-top col-gap">
    <div class="cols-5 ">
        <table class="cols-full">
            <?php if($correspondeceApp === "on") { ?>
            <tr>
                <td class="data-label"><?=\CHtml::encode($element->getAttributeLabel('is_signed_off')) . ' '; ?></td>
                <td>
                    <div class="data-value" style="text-align: right">
                        <?php
                        if($element->is_signed_off == NULL){
                            echo 'N/A';
                        } else if((int)$element->is_signed_off == 1){
                            echo 'Yes';
                        } else {
                            echo 'No';
                        }
                        ?>
                    </div>
                </td>
            </tr>
            <?php } ?>
            <tr>
                <td class="data-label">
                    From
                </td>
                <td>
                    <?php
                    echo $element->site->getLetterAddress(array(
                        'include_name' => true,
                        'delimiter' => '<br />',
                        'include_telephone' => true,
                        'include_fax' => true,
                    ))?>
                </td>
            </tr>
            <tr>
                <td class="data-label">
                    Direct Line
                </td>
                <td>
                    <?php
                    echo $element->direct_line
                    ?>
                </td>
            </tr>
            <tr>
                <td class="data-label">
                    Direct Fax
                </td>
                <td >
                    <?php
                    echo $element->fax
                    ?>
                </td>
            </tr>
            <tr>
                <td class="data-label">
                    Cc
                </td>
                <td>
                    <?php
                    $ccString = "";
                    $toAddress = "";

                    if($element->document_instance) {

                        foreach ($element->document_instance as $instance) {
                            foreach ($instance->document_target as $target) {
                                if($target->ToCc == 'To'){
                                    $toAddress = $target->contact_name . "\n" . $target->address;
                                } else {
                                    $contact_type = $target->contact_type != 'GP' ? ucfirst(strtolower($target->contact_type)) : $target->contact_type;

                                    $ccString .= "" . $contact_type . ": " . $target->contact_name . ", " . $element->renderSourceAddress($target->address)."<br/>";
                                }
                            }
                        }
                    }else
                    {
                        $toAddress = $element->address;
                        foreach (explode("\n", trim($element->cc)) as $line) {
                            if (trim($line)) {
                                $ccString .= "" . str_replace(';', ',', $line)."<br/>";
                            }
                        }
                    }
                    ?>
                    <?php
                    echo $ccString
                    ?>
                </td>
            </tr>
        </table>
    </div>
    <div class="spinner-overlay">
        <i class="spinner"></i>
        <img src="#"
             width="<?=Yii::app()->params['lightning_viewer']['blank_image_template']['width']?>"
             height="<?=Yii::app()->params['lightning_viewer']['blank_image_template']['height']?>"
             style="background-color: white;"
        >
    </div>
    <div id="correspondence_out"
         class="wordbreak correspondence-letter<?php if ($element->draft) {?> draft<?php }?> cols-7 element"
         style="background-color: white; color: black; display:none;">
            <header>
                <?php
            $ccString = "";
            $toAddress = "";
             if($element->document_instance) {
                 foreach ($element->document_instance as $instance) {
                    foreach ($instance->document_target as $target) {
                        if($target->ToCc == 'To'){
                            $toAddress = $target->contact_name . "\n" . $target->address;
                        } else {
                            $contact_type = $target->contact_type != 'GP' ? ucfirst(strtolower($target->contact_type)) : $target->contact_type;
                             $ccString .= "CC: " . $contact_type . ": " . $target->contact_name . ", " . $element->renderSourceAddress($target->address)."<br/>";
                        }
                    }
                }
            }else
            {
                $toAddress = $element->address;
                foreach (explode("\n", trim($element->cc)) as $line) {
                    if (trim($line)) {
                        $ccString .= "CC: " . str_replace(';', ',', $line)."<br/>";
                    }
                }
            }
            $this->renderPartial('letter_start', array(
                'toAddress' => $toAddress,
                'patient' => $this->patient,
                'date' => $element->date,
                'clinicDate' => $element->clinic_date,
                'element' => $element,
            ));
                    ?>
            </header>
            <?php
            $this->renderPartial('reply_address', array(
                'site' => $element->site,
                'is_internal_referral' => $element->isInternalReferral(),
            ));
            $this->renderPartial('print_ElementLetter', array(
                'element' => $element,
                'toAddress' => $toAddress,
                'ccString' => $ccString,
                'no_header' => true,
            ));
            $is_document = isset($element->document_instance);
            ?>
            <input type="hidden" name="OphCoCorrespondence_printLetter" id="OphCoCorrespondence_printLetter" value="<?php echo $element->print?>" />
            <?php if(Yii::app()->user->getState('correspondece_element_letter_saved', true)): ?>
                <?php Yii::app()->user->setState('correspondece_element_letter_saved', false); ?>
                <input type="hidden" name="OphCoCorrespondence_print_checked" id="OphCoCorrespondence_print_checked" value="<?php echo $is_document ? '1' : '0'; ?>" />
            <?php else: ?>
                <input type="hidden" name="OphCoCorrespondence_printLetter" id="OphCoCorrespondence_printLetter_all" value="<?php echo $element->print_all?>" />
            <?php endif; ?>
        </div>
<div class="js-correspondence-image-overlay">
</div>

</div>
<script type="text/javascript">
    $(document).ready(function () {
        new OpenEyes.OphCoCorrespondence.ImageLoaderController(OE_event_id , []);
    });
</script>
