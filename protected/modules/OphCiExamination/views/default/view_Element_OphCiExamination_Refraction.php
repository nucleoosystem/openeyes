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
<div class="element-data element-eyes">
    <?php foreach (['left' => 'right', 'right' => 'left'] as $page_side => $eye_side): ?>
        <div class="js-element-eye <?= $eye_side ?>-eye column">
            <table class="large">
                <colgroup>
                    <col class="cols-4">
                    <col class="cols-3">
                </colgroup>
                <tbody>

                <?php if ($element->hasEye($eye_side)): ?>
                    <tr>
                        <td><?= Yii::app()->format->text($element->getCombined($eye_side)) ?></td>
                        <?php $SE = $element->{$eye_side . '_sphere'} + 0.5 * $element->{$eye_side . '_cylinder'};?>
                        <td>SE: <?=$SE > 0 ? '+' : ''?><?= number_format($SE, 2) ?></td>
                        <td><?= Yii::app()->format->text($element->getType($eye_side)) ?></td>
                    </tr>
                    <tr>
                        <?php if ($element->{$eye_side . '_notes'}): ?>
                            <td colspan="3"><?php echo $element->textWithLineBreaks($eye_side . '_notes') ?></td>
                        <?php endif; ?>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="3">
                            <div class="data-value not-recorded">Not recorded</div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
</div>
