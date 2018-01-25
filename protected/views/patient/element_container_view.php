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
<section
	class=" element full priority
<!--	--><?php //if (@$child) { echo 'tile'; } else { echo 'full priority';} ?><!--  -->
	view-<?php echo CHtml::modelName($element->elementType->class_name)?>"
	data-element-type-id="<?php echo $element->elementType->id?>"
	data-element-type-class="<?php echo $element->elementType->class_name?>"
	data-element-type-name="<?php echo $element->elementType->name?>"
	data-element-display-order="<?php echo $element->elementType->display_order?>">
	<?php if (!preg_match('/\[\-(.*)\-\]/', $element->elementType->name)) { ?>
		<header class=" element-header">
			<h3 class="element-title"><?php echo $element->elementType->name ?></h3>
		</header>
	<?php } ?>
	<?php echo $content;?>
</section>

<?php $child_elements = $this->getChildElements($element->getElementType());
  for ($i=0; $i<sizeof($child_elements); $i++) {
//      if ($i % 3 == 0) {
          ?>
        <div class="flex-layout flex-left flex-stretch">
<!--      --><?php //}
  $this->renderChildOpenElements($child_elements[$i], 'view', @$form, @$data);
//      if ($i % 3 == 2 || $i==sizeof($child_elements)-1) {
//          ?>
        </div>
<!--      --><?php //}
  }?>