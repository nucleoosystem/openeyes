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

<div class="oe-popup-wrap" id="js-delete-event" style="display: none; z-index:100">
    <div class="oe-popup">
        <?php echo CHtml::form(array('Default/delete/' . $this->event->id), 'post', array('id' => 'deleteForm')) ?>
        <div class="title">
            <i class="oe-i trash large selected"></i>
            Delete Event
        </div>
        <div class="oe-popup-content delete-event">

            <div class="alert-box warning">
                <strong>WARNING: This will permanently delete the event and remove it from view.<br>THIS ACTION CANNOT BE UNDONE.</strong>
                <?php $this->displayErrors(@$errors) ?>
                <p id="errors"></p>
            </div>
            <table class="standard row">
                <tbody>
                <tr>
                    <td>Delete:</td>
                    <td class="flex-layout">
                        <i class="oe-i-e <?php echo $this->event->eventType->getEventIconCssClass()?>"></i>
                        <h4><?php echo $this->event->eventType->name ?> <?php echo Helper::convertDate2NHS($this->event->event_date)?></h4>
                    </td>
                </tr>
                <tr>
                    <td>Reason for deletion:</td>
                    <td><?php echo CHtml::textArea('delete_reason', '', array('cols' => 40,'id' => 'js-text-area')) ?></textarea></td>
                </tr>
                </tbody>
            </table>
            <div class="flex-layout row">
                <h4>Are you sure you want to proceed? </h4>
                <?php
                echo CHtml::hiddenField('event_id', $this->event->id); ?>
                <button type="submit" class="large red hint" id="et_deleteevent" name="et_deleteevent">
                    Delete event
                </button>
                <button type="submit" class="large blue hint cancel-icon-btn" id="et_canceldelete" name="et_canceldelete">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    <?php echo CHtml::endForm(); ?>
</div>


