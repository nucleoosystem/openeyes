<?php
/**
 * OpenEyes.
 *
 * (C) OpenEyes Foundation, 2016
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2016, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * Class BaseEventTypeController.
 *
 * BaseEventTypeController is the base controller for modules managing events within OpenEyes.
 *
 * It implements a standardised design pattern to provide the general CRUD interface for module events. The controller
 * is designed to be stateful. When an action is called, the state of the controller is determined from the POST and GET
 * attributes of the request. Properties on the controller are populated through a series of methods, and the response
 * is rendered based on these values, and returned to the user. The rationale behind this is that each of the methods
 * provide discrete hooks which can be overridden in module controllers to redefine what the controller properties
 * should be set to.
 *
 * The primary property of the controller to be manipulated is the {@link open_elements} which defines the elements of
 * the event to be displayed in whatever action is being performed.
 *
 * An abstract class in all but name, it should be used for all event based modules. Specific methods can be implemented
 * in module level controllers that will be called automatically by this base controller. Specifically setting defaults
 * on elements and setting complex attributes on individual elements can be handled in specific methods, as defined by
 * <ul>
 * <li>{@link setElementDefaultOptions}</li>
 * <li>{@link setElementComplexAttributesFromData}</li>
 * <li>{@link saveElementComplexAttributesFromData}</li>
 * </ul>
 *
 * It's worth noting that at the moment there is no class for Events at the module level. As a result, the controller
 * tends to contain certain business logic that should really be part of the event. Such behaviour should be written in
 * a way that it can be easily extracted into a separate class. The intention in the future is that this would be abstracted
 * into (at a minimum) a helper class, or ideally into an actual event class that would contain all business logic for
 * manipulating the event and its elements.
 *
 * Furthermore no $_POST, $_GET or session data should be utilised within the element models. Data should be extracted
 * by controllers and passed to methods on the element models. In the future, models may be instantiated in different
 * context where these globals would not be available.
 */
class BaseEventTypeController extends BaseModuleController
{
    const ACTION_TYPE_CREATE = 'Create';
    const ACTION_TYPE_VIEW = 'View';
    const ACTION_TYPE_PRINT = 'Print';
    const ACTION_TYPE_EDIT = 'Edit';
    const ACTION_TYPE_DELETE = 'Delete';
    const ACTION_TYPE_REQUESTDELETE = 'RequestDelete';
    const ACTION_TYPE_FORM = 'Form';    // AJAX actions that are used during create and update but don't actually modify data themselves

    private $unique_code_elements = array(
        array('event' => 'OphTrOperationnote', 'element' => array('Element_OphTrOperationnote_Cataract')),
        array('event' => 'OphCoCvi', 'element' => array('Element_OphCoCvi_EventInfo')),
    );

    private static $base_action_types = array(
        'create' => self::ACTION_TYPE_CREATE,
        'view' => self::ACTION_TYPE_VIEW,
        'elementForm' => self::ACTION_TYPE_FORM,
        'viewPreviousElements' => self::ACTION_TYPE_FORM,
        'print' => self::ACTION_TYPE_PRINT,
        'PDFprint' => self::ACTION_TYPE_PRINT,
        'saveCanvasImages' => self::ACTION_TYPE_PRINT,
        'update' => self::ACTION_TYPE_EDIT,
        'delete' => self::ACTION_TYPE_DELETE,
        'requestDeletion' => self::ACTION_TYPE_REQUESTDELETE,
        'eventImage' => self::ACTION_TYPE_VIEW,
        'printCopy' => self::ACTION_TYPE_PRINT,
        'savePDFprint' => self::ACTION_TYPE_PRINT,
        'createImage' => self::ACTION_TYPE_VIEW,
    );

    /**
     * Override for custom actions.
     *
     * @var array
     */
    protected static $action_types = array();

    /* @var Patient */
    public $patient;
    /* @var Site */
    public $site;
    /* @var Event */
    public $event;
    public $editable = true;
    public $editing;
    private $title;
    public $episode;
    public $moduleStateCssClass = '';
    public $event_tabs = array();
    public $event_actions = array();
    public $successUri = 'default/view/';
    // String to set an issue when an event is created
    public $eventIssueCreate;
    // defines additional variables to be available in view templates
    public $extraViewProperties = array();
    public $layout = '//layouts/events_and_episodes';
    private $action_type_map;
    private $episodes = array();
    public $renderPatientPanel = true;

    protected $open_elements;
    public $dont_redirect = false;
    public $pdf_print_suffix = null;
    public $pdf_print_documents = 1;
    public $pdf_print_html = null;
    public $attachment_print_title = null;

    /**
     * Values to change per event
     *
     * @var float $resolution_multiplier how much to 'zoom in' on the pdf when changing to png
     * @var int $image_width width of preview image in pixels
     * @var int $compression_quality from 1 (lowest) to 100 (highest)
     */
    public $resolution_multiplier = 1;
    public $image_width = 800;
    public $compression_quality = 50;

    /**
     * @var int $element_tiles_wide The number of tiles that can be rendered in a single row
     */
    protected $element_tiles_wide = 3;

    /**
     * Set to false if the event list should remain on the sidebar when creating/editing the event
     *
     * @var bool
     */
    protected $show_element_sidebar = true;

    /**
     * Set to true if the index search bar should appear in the header when creating/editing the event
     *
     * @var bool
     */
    protected $show_index_search = false;

    public function behaviors()
    {
        return array(
            'CreateEventBehavior' => array(
                'class' => 'application.behaviors.CreateEventControllerBehavior',
            ),
        );
    }

    public function getPageTitle()
    {
        return ucfirst($this->getAction()->getId()) .
            ($this->event_type ? ' ' . $this->event_type->name : '') .
            ($this->patient ? ' - ' . $this->patient->last_name . ', ' . $this->patient->first_name : '') .
            ' - OE';
    }

    public function getTitle()
    {
        if (isset($this->title)) {
            return $this->title;
        }
        if (isset($this->event_type)) {
            return $this->event_type->name;
        }

        return '';
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function init()
    {
        $this->action_type_map = array();
        foreach (self::$base_action_types as $action => $type) {
            $this->action_type_map[strtolower($action)] = $type;
        }
        foreach (static::$action_types as $action => $type) {
            $this->action_type_map[strtolower($action)] = $type;
        }

        return parent::init();
    }

    public function accessRules()
    {
        // Allow logged in users - the main authorisation check happens later in verifyActionAccess
        return array(array('allow', 'users' => array('@')));
    }

    /**
     * Wrapper around the episode property on this controller - current_episode is used in patient layouts.
     *
     * @return Episode
     */
    public function getCurrent_episode()
    {
        return $this->episode;
    }

    /**
     * Return an ACTION_TYPE_ constant representing the type of an action for authorisation purposes.
     *
     * @param string $action
     *
     * @throws Exception
     *
     * @return string
     */
    public function getActionType($action)
    {
        if (!isset($this->action_type_map[strtolower($action)])) {
            throw new Exception("Action '{$action}' has no type associated with it");
        }

        return $this->action_type_map[strtolower($action)];
    }

    /**
     * @param $action
     * @return int
     */
    public function getElementWidgetMode($action)
    {
        $action_type = $this->getActionType($action);
        return in_array($action_type,
            array(static::ACTION_TYPE_CREATE, static::ACTION_TYPE_EDIT, static::ACTION_TYPE_FORM))
            ? BaseEventElementWidget::$EVENT_EDIT_MODE
            : ($action_type === static::ACTION_TYPE_PRINT
                ? BaseEventElementWidget::$EVENT_PRINT_MODE
                : BaseEventElementWidget::$EVENT_VIEW_MODE);
    }

    /**
     * Sets the patient object on the controller.
     *
     * @param $patient_id
     *
     * @throws CHttpException
     */
    protected function setPatient($patient_id)
    {
        if (!$this->patient = Patient::model()->findByPk($patient_id)) {
            throw new CHttpException(404, 'Invalid patient_id.');
        }
    }

    /**
     * Abstraction of getting the elements for the event being controlled to allow more complex overrides (such as workflow)
     * where required.
     *
     * This should be overridden if the standard elements for the event are affected by the controller state.
     *
     * @return BaseEventTypeElement[]
     */
    protected function getEventElements()
    {
        if ($this->event && !$this->event->isNewRecord) {
            return $this->event->getElements();
        } else {
            return $this->event_type->getDefaultElements();
        }
    }

    /**
     * based on the current state of the controller, sets the open_elements property, which is the array of relevant
     * open elements for the controller.
     */
    protected function setOpenElementsFromCurrentEvent($action)
    {
        $this->open_elements = $this->getEventElements();
        $this->setElementOptions($action);

        // Ensure the element type is initialised so getDisplayOrder() doesn't mutate array during sorting
        array_map(function ($e) {
            $e->getElementType();
            if ($e->getElementType()->parent_element_type) {
                $e->getElementType()->parent_element_type->display_order;
            }
        }, $this->open_elements);

        usort($this->open_elements, function ($a, $b) use ($action) {
            $a_parent_order = (int)$a->getParentDisplayOrder($action);
            $b_parent_order = (int)$b->getParentDisplayOrder($action);
            $a_child_order = (int)$a->getChildDisplayOrder($action);
            $b_child_order = (int)$b->getChildDisplayOrder($action);

            if ($a_parent_order === $b_parent_order) {
                return $a_child_order < $b_child_order ? -1 : 1;
            } else {
                return $a_parent_order < $b_parent_order ? -1 : 1;
            }
        }
        );
    }

    /**
     * Renders the metadata of the event with the standard template.
     *
     * @param string $view
     */
    public function renderEventMetadata($view = '//patient/event_metadata')
    {
        $this->renderPartial($view);
    }

    /**
     * Get the open elements for the event that are not children.
     *
     * @return array
     */
    public function getElements($action='edit')
    {
        $elements = array();
        if (is_array($this->open_elements)) {
            foreach ($this->open_elements as $element) {
                if ($element->getElementType()) {
                    $elements[] = $element;
                }
            }
        }
        return $elements;
    }

    /**
     * @return ElementType[]
     */
    protected function getAllElementTypes()
    {
        return $this->event_type->getAllElementTypes();
    }

    /**
     * @param array $remove_list
     * @return string
     */
    public function getElementTree($remove_list = array())
    {
        $element_types_tree = array();
        foreach ($this->event_type->getRootElementTypes() as $et) {
            if (count($remove_list) && in_array($et->class_name, $remove_list)) {
                continue;
            }
            $struct = array(
                'name' => $et->group_title ?: $et->name,
                'class_name' => CHtml::modelName($et->class_name),
                'id' => $et->id,
                'display_order' => -1,
                'parent_display_order' => $et->display_order,
                'children' => array(),
            );

            if (count($et->child_element_types) > 0) {
                // Add the parent as its first child with the display name instead of the group title
                $struct['children'][] = array(
                    'name' => $et->name,
                    'class_name' => CHtml::modelName($et->class_name),
                    'id' => $et->id,
                    'display_order' => -1,
                    'parent_display_order' => $et->display_order,
                );

                foreach ($et->child_element_types as $child) {
                    if (count($remove_list) && in_array($child->class_name, $remove_list)) {
                        continue;
                    }
                    $struct['children'][] = array(
                        'name' => $child->name,
                        'id' => $child->id,
                        'display_order' => $child->display_order,
                        'parent_display_order' => $et->display_order,
                        'class_name' => CHtml::modelName($child->class_name),
                    );
                }
            }
            $element_types_tree[] = $struct;
        }

        return json_encode($element_types_tree);
    }

    /**
     * Get the open child elements for the given ElementType.
     *
     * @param ElementType $parent_type
     *
     * @return \BaseEventTypeElement[] $open_elements
     */
    public function getChildElements($parent_type, $action = 'edit')
    {
        $open_child_elements = array();
        if (is_array($this->open_elements)) {
            foreach ($this->open_elements as $open) {
                $et = $open->getElementType();
                if ($et && $open->isChild($action) && $open->getParentType($action) == $parent_type->class_name) {
                    $open_child_elements[] = $open;
                }
            }
        }

        if($action === 'view') {
            usort($open_child_elements, function ($a, $b) {
                $a_order = $a->getDisplayOrder('view');
                $b_order = $b->getDisplayOrder('view');
                if ($a_order == $b_order) {
                    return 0;
                }
                return ($a_order < $b_order) ? -1 : 1;
            }
            );
        }
        return $open_child_elements;
    }

    /**
     * Get the optional elements for the current module's event type (that are not children).
     *
     * @return BaseEventTypeElement[] $elements
     */
    public function getOptionalElements()
    {
        $open_et = array();
        foreach ($this->open_elements as $open) {
            $open_et[] = get_class($open);
        }

        $optional = array();
        foreach ($this->event_type->getAllElementTypes() as $element_type) {
            if (!in_array($element_type->class_name, $open_et) && !$element_type->isChild()) {
                $optional[] = $element_type->getInstance();
            }
        }

        return $optional;
    }

    /**
     * Get the child optional elements for the given element type.
     *
     * @param ElementType $parent_type
     *
     * @return BaseEventTypeElement[] $optional_elements
     */
    public function getChildOptionalElements($parent_type)
    {
        $open_et = array();
        if (is_array($this->open_elements)) {
            foreach ($this->open_elements as $open) {
                $et = $open->getElementType();
                if ($et && $et->isChild() && $et->parent_element_type->class_name == $parent_type->class_name) {
                    $open_et[] = $et->class_name;
                }
            }
        }
        $optional = array();
        foreach ($parent_type->child_element_types as $child_type) {
            if (!in_array($child_type->class_name, $open_et)) {
                $optional[] = $child_type->getInstance();
            }
        }

        return $optional;
    }

    /**
     * Override to use $action_types.
     *
     * @param string $action
     *
     * @return bool
     */
    public function isPrintAction($action)
    {
        return self::getActionType($action) == self::ACTION_TYPE_PRINT;
    }

    /**
     * Setup base css/js etc requirements for the eventual action render.
     *
     * @param $action
     *
     * @return bool
     *
     * @throws CHttpException
     *
     * @see parent::beforeAction($action)
     */
    protected function beforeAction($action)
    {
        // Automatic file inclusion unless it's an ajax call
        if ($this->assetPath && !Yii::app()->getRequest()->getIsAjaxRequest()) {
            if (!$this->isPrintAction($action->id)) {
                // nested elements behaviour
                //TODO: possibly put this into standard js library for events
                Yii::app()->getClientScript()->registerScript('nestedElementJS', 'var moduleName = "' . $this->getModule()->name . '";', CClientScript::POS_HEAD);
                Yii::app()->assetManager->registerScriptFile('js/nested_elements.js');
                Yii::app()->assetManager->registerScriptFile("js/OpenEyes.UI.InlinePreviousElements.js");
            }
        }

        $this->setFirmFromSession();

        if (!isset($this->firm)) {
            // No firm selected, reject
            throw new CHttpException(403, 'You are not authorised to view this page without selecting a firm.');
        }

        $this->initAction($action->id);

        $this->verifyActionAccess($action);

        return parent::beforeAction($action);
    }

    /**
     * Redirect to the patient episodes when the controller determines the action cannot be carried out.
     */
    protected function redirectToPatientEpisodes()
    {
        $this->redirect(array('/patient/episodes/' . $this->patient->id));
    }

    /**
     * set the defaults on the given BaseEventTypeElement.
     *
     * Looks for a methods based on the class name of the element:
     * setElementDefaultOptions_[element class name]
     *
     * This method is passed the element and action, which allows for controller methods to manipulate the default
     * values of the element (if the controller state is required for this)
     *
     * @param BaseEventTypeElement $element
     * @param string $action
     */
    protected function setElementDefaultOptions($element, $action)
    {
        if ($action == 'create') {
            $element->setDefaultOptions($this->patient);
        } elseif ($action == 'update') {
            $element->setUpdateOptions();
        }

        $el_method = 'setElementDefaultOptions_' . Helper::getNSShortname($element);
        if (method_exists($this, $el_method)) {
            $this->$el_method($element, $action);
        }
    }

    /**
     * Set the default values on each of the open elements.
     *
     * @param string $action
     */
    protected function setElementOptions($action)
    {
        foreach ($this->open_elements as $element) {
            $this->setElementDefaultOptions($element, $action);
        }
    }

    protected function getPrevious($element_type, $exclude_event_id = null)
    {
        if ($api = $this->getApp()->moduleAPI->get($this->getModule()->name)) {
            return array_filter(
                $api->getElements($element_type->class_name, $this->patient, false),
                function ($el) use ($exclude_event_id) {
                    return $el->event_id != $exclude_event_id;
                });
        } else {
            return array();
        }
    }

    /**
     * Are there one or more previous instances of an element?
     *
     * @param ElementType $element_type
     * @param int $exclude_event_id
     *
     * @return bool
     */
    public function hasPrevious($element_type, $exclude_event_id = null)
    {
        return count($this->getPrevious($element_type, $exclude_event_id)) > 0;
    }

    /**
     * Can an element can be copied from a previous version.
     *
     * @param BaseEventTypeElement $element
     *
     * @return bool
     */
    public function canCopy($element)
    {
        return $element->canCopy() && $this->hasPrevious($element->getElementType(), $element->event_id);
    }

    /**
     * Can we view the previous version of the element.
     *
     * @param BaseEventTypeElement $element
     *
     * @return bool
     */
    public function canViewPrevious($element)
    {
        return $element->canViewPrevious() && $this->hasPrevious($element->getElementType(), $element->event_id);
    }

    /**
     * Is this a required element?
     *
     * @param BaseEventTypeElement $element
     *
     * @return bool
     */
    public function isRequired(BaseEventTypeElement $element)
    {
        return $element->isRequired();
    }

    /**
     * Is this element required in the UI? (Prevents the user from being able
     * to remove the element.).
     *
     * @param BaseEventTypeElement $element
     *
     * @return bool
     */
    public function isRequiredInUI(BaseEventTypeElement $element)
    {
        return $element->isRequiredInUI();
    }

    /**
     * Is this element to be hidden in the UI? (Prevents the elements from
     * being displayed on page load.).
     *
     * @param BaseEventTypeElement $element
     *
     * @return bool
     */
    public function isHiddenInUI(BaseEventTypeElement $element)
    {
        return $element->isHiddenInUI();
    }

    /**
     * Initialise an element of $element_type for returning as an individual form. If the $previous_id is provided,
     * then the default values of the element will be overridden with the properties of the previous intance of the
     * element. Similarly, $additional allows specific values to be set on the element.
     *
     * Abstracted to allow overrides in specific module controllers
     *
     * @param ElementType $element_type
     * @param int $previous_id
     * @param array()     $additional   - additional attributes for the element
     *
     * @return \BaseEventTypeElement
     */
    protected function getElementForElementForm($element_type, $previous_id = 0, $additional)
    {
        $element_class = $element_type->class_name;
        $element = $element_type->getInstance();
        $this->setElementDefaultOptions($element, 'create');

        if ($previous_id && $element->canCopy()) {
            $previous_element = $element_class::model()->findByPk($previous_id);
            $element->loadFromExisting($previous_element);
        }
        if ($additional) {
            foreach (array_keys($additional) as $add) {
                if ($element->isAttributeSafe($add)) {
                    $element->$add = $additional[$add];
                }
            }
        }

        return $element;
    }

    /**
     * Runs initialisation of the controller based on the action. Looks for a method name of.
     *
     * initAction[$action]
     *
     * and calls it.
     *
     * @param string $action
     */
    protected function initAction($action)
    {
        $init_method = 'initAction' . ucfirst($action);
        if (method_exists($this, $init_method)) {
            $this->$init_method();
        }
    }

    /**
     * Initialise the controller prior to a create action.
     *
     * @throws CHttpException
     */
    protected function initActionCreate()
    {
        $this->moduleStateCssClass = 'edit';

        $this->setPatient($_REQUEST['patient_id']);

        if (!$this->episode = $this->getEpisode()) {
            $this->redirectToPatientEpisodes();
        }

        // we instantiate an event object for use with validation rules that are dependent
        // on episode and patient status
        $this->event = new Event();
        $this->event->episode_id = $this->episode->id;
        $this->event->event_type_id = $this->event_type->id;
    }

    /**
     * Intialise controller property based off the event id.
     *
     * @param $id
     *
     * @throws CHttpException
     */
    protected function initWithEventId($id)
    {
        $criteria = new CDbCriteria();
        $criteria->addCondition('event_type_id = ?');
        $criteria->params = array($this->event_type->id);
        if (!$id || !$this->event = Event::model()->findByPk($id, $criteria)) {
            throw new CHttpException(404, 'Invalid event id.');
        }

        $this->patient = $this->event->episode->patient;
        $this->episode = $this->event->episode;
    }

    /**
     * Sets the the css state.
     */
    protected function initActionView()
    {
        $this->readInEventImageSettings();
        $this->moduleStateCssClass = 'view';
        $this->initWithEventId(@$_GET['id']);
    }

    /**
     * initialise the controller prior to event update action.
     *
     * @throws CHttpException
     */
    protected function initActionUpdate()
    {
        $this->moduleStateCssClass = 'edit';

        $this->initWithEventId(@$_GET['id']);
    }

    /**
     * initialise the controller with the event id.
     */
    protected function initActionDelete()
    {
        $this->initWithEventId(@$_GET['id']);

        //on soft delete we call the afterSoftDelete method
        $this->event->getEventHandlers('onAfterSoftDelete')->add(array($this, 'afterSoftDelete'));
    }

    /**
     * @param CAction $action
     */
    protected function verifyActionAccess(CAction $action)
    {
        $actionType = $this->getActionType($action->id);
        $method = "check{$actionType}Access";

        if (!method_exists($this, $method)) {
            throw new Exception("No access check method found for action type '{$actionType}'");
        }

        if (!$this->$method()) {
            switch ($actionType) {
                case self::ACTION_TYPE_CREATE:
                case self::ACTION_TYPE_EDIT:
                    $this->redirectToPatientEpisodes();
                    break;
                default:
                    throw new CHttpException(403);
            }
        }
    }

    /**
     * @return bool
     */
    public function checkCreateAccess()
    {
        return $this->checkAccess('OprnCreateEvent', $this->firm, $this->episode, $this->event_type);
    }

    /**
     * @return bool
     */
    public function checkViewAccess()
    {
        return $this->checkAccess('OprnViewClinical');
    }

    /**
     * @return bool
     */
    public function checkPrintAccess()
    {
        return $this->checkAccess('OprnPrint');
    }

    /**
     * @return bool
     */
    public function checkEditAccess()
    {
        return $this->checkAccess('OprnEditEvent', $this->firm, $this->event);
    }

    /**
     * @return bool
     */
    public function checkDeleteAccess()
    {
        return $this->checkAccess('OprnDeleteEvent', Yii::app()->session['user'], $this->firm, $this->event);
    }

    /**
     * @return bool
     */
    public function checkRequestDeleteAccess()
    {
        return $this->checkAccess('OprnRequestEventDeletion', $this->firm, $this->event);
    }

    /**
     * @return bool
     */
    public function checkFormAccess()
    {
        return $this->checkAccess('OprnViewClinical');
    }

    /**
     * @return bool
     */
    public function checkAdminAccess()
    {
        return $this->checkAccess('admin');
    }

    /**
     * Carries out the base create action.
     *
     * @return bool|string
     *
     * @throws CHttpException
     * @throws Exception
     */
    public function actionCreate()
    {
        $this->event->firm_id = $this->selectedFirmId;
        if (!empty($_POST)) {
            // form has been submitted
            if (isset($_POST['cancel'])) {
                $this->redirectToPatientEpisodes();
            }

            // set and validate
            $errors = $this->setAndValidateElementsFromData($_POST);

            // creation
            if (empty($errors)) {
                $transaction = Yii::app()->db->beginTransaction();

                try {
                    $success = $this->saveEvent($_POST);

                    if ($success) {
                        //TODO: should this be in the save event as pass through?
                        if ($this->eventIssueCreate) {
                            $this->event->addIssue($this->eventIssueCreate);
                        }
                        //TODO: should not be passing event?
                        $this->afterCreateElements($this->event);

                        $this->logActivity('created event.');

                        $this->event->audit('event', 'create');

                        Yii::app()->user->setFlash('success', "{$this->event_type->name} created.");

                        $transaction->commit();

                        if ($this->event->parent_id) {
                            $this->redirect(Yii::app()->createUrl('/' . $this->event->parent->eventType->class_name . '/default/view/' . $this->event->parent_id));
                        } else {
                            $this->redirect(array($this->successUri . $this->event->id));
                        }
                    } else {
                        throw new Exception('could not save event');
                    }
                } catch (Exception $e) {
                    $transaction->rollback();
                    throw $e;
                }
            }
        } else {
            $this->setOpenElementsFromCurrentEvent('create');
            $this->updateHotlistItem($this->patient);
        }

        $this->editable = false;
        $this->event_tabs = array(
            array(
                'label' => 'Create',
                'active' => true,
            ),
        );

        $cancel_url = ($this->episode) ? '/patient/episode/' . $this->episode->id : '/patient/episodes/' . $this->patient->id;
        $this->event_actions = array(
            EventAction::link('Cancel',
                Yii::app()->createUrl($cancel_url),
                array('level' => 'cancel')
            ),
        );

        $this->render('create', array(
            'errors' => @$errors,
        ));
    }

    /**
     * View the event specified by $id.
     *
     * @param $id
     *
     * @throws CHttpException
     */
    public function actionView($id)
    {
        $this->setOpenElementsFromCurrentEvent('view');
        // Decide whether to display the 'edit' button in the template
        if ($this->editable) {
            $this->editable = $this->checkEditAccess();
        }

        $this->logActivity('viewed event');

        $this->event->audit('event', 'view');

        $this->event_tabs = array(
            array(
                'label' => 'View',
                'active' => true,
            ),
        );
        if ($this->editable) {
            $this->event_tabs[] = array(
                'label' => 'Edit',
                'href' => Yii::app()->createUrl($this->event->eventType->class_name . '/default/update/' . $this->event->id),
            );
        }

        if ($this->checkDeleteAccess()) {
            $this->event_actions = array(
                EventAction::link('Delete',
                    Yii::app()->createUrl($this->event->eventType->class_name . '/default/delete/' . $this->event->id),
                    array('level' => 'delete')
                ),
            );
        } elseif ($this->checkRequestDeleteAccess()) {
            $this->event_actions = array(
                EventAction::link('Delete',
                    Yii::app()->createUrl($this->event->eventType->class_name . '/default/requestDeletion/' . $this->event->id),
                    array('level' => 'delete')
                ),
            );
        }

        $viewData = array_merge(array(
            'elements' => $this->open_elements,
            'eventId' => $id,
        ), $this->extraViewProperties);

        $this->jsVars['OE_event_last_modified'] = strtotime($this->event->last_modified_date);

        $this->render('view', $viewData);
    }

    /**
     * The update action for the given event id.
     *
     * @param $id
     *
     * @throws CHttpException
     * @throws SystemException
     * @throws Exception
     */
    public function actionUpdate($id)
    {
        if (!empty($_POST)) {
            // somethings been submitted
            if (isset($_POST['cancel'])) {
                // Cancel button pressed, so just bounce to view
                $this->redirect(array('default/view/' . $this->event->id));
            }

            $errors = $this->setAndValidateElementsFromData($_POST);

            // update the event
            if (empty($errors)) {
                $transaction = Yii::app()->db->beginTransaction();

                try {
                    //TODO: should all the auditing be moved into the saving of the event
                    $success = $this->saveEvent($_POST);

                    if ($success) {
                        //TODO: should not be pasing event?
                        $this->afterUpdateElements($this->event);
                        $this->logActivity('updated event');

                        $this->event->audit('event', 'update');

                        $this->event->user = Yii::app()->user->id;

                        if (!$this->event->save()) {
                            throw new SystemException('Unable to update event: ' . print_r($this->event->getErrors(), true));
                        }

                        OELog::log("Updated event {$this->event->id}");
                        $transaction->commit();
                        if ($this->event->parent_id) {
                            $this->redirect(Yii::app()->createUrl('/' . $this->event->parent->eventType->class_name . '/default/view/' . $this->event->parent_id));
                        } else {
                            $this->redirect(array('default/view/' . $this->event->id));
                        }
                    } else {
                        throw new Exception('Unable to save edits to event');
                    }
                } catch (Exception $e) {
                    $transaction->rollback();
                    throw $e;
                }
            }
        } else {
            // get the elements
            $this->setOpenElementsFromCurrentEvent('update');
            $this->updateHotlistItem($this->patient);
        }

        $this->editing = true;
        $this->event_tabs = array(
            array(
                'label' => 'View',
                'href' => Yii::app()->createUrl($this->event->eventType->class_name . '/default/view/' . $this->event->id),
            ),
            array(
                'label' => 'Edit',
                'active' => true,
            ),
        );

        $this->event_actions = array(
            EventAction::link('Cancel',
                Yii::app()->createUrl($this->event->eventType->class_name . '/default/view/' . $this->event->id),
                array('level' => 'cancel')
            ),
        );

        $this->render($this->action->id, array(
            'errors' => @$errors,
        ));
    }

    /**
     * Ajax method for loading an individual element (and its children).
     *
     * @param int $id
     * @param int $patient_id
     * @param int $previous_id
     *
     * @throws CHttpException
     * @throws Exception
     *
     * @internal param int $import_previous
     */
    public function actionElementForm($id, $patient_id, $previous_id = null)
    {
        // first prevent invalid requests
        $element_type = ElementType::model()->findByPk($id);
        if (!$element_type) {
            throw new CHttpException(404, 'Unknown ElementType');
        }
        $patient = Patient::model()->findByPk($patient_id);
        if (!$patient) {
            throw new CHttpException(404, 'Unknown Patient');
        }

        // Clear script requirements as all the base css and js will already be on the page
        Yii::app()->assetManager->reset();

        $this->patient = $patient;

        $this->setFirmFromSession();

        $this->episode = $this->getEpisode();

        // allow additional parameters to be defined by module controllers
        // TODO: Should valid additional parameters be a property of the controller?
        $additional = array();
        foreach (array_keys($_GET) as $key) {
            if (!in_array($key, array('id', 'patient_id', 'previous_id'))) {
                $additional[$key] = $_GET[$key];
            }
        }

        // retrieve the element
        $element = $this->getElementForElementForm($element_type, $previous_id, $additional);
        $this->open_elements = array($element);

        $form = Yii::app()->getWidgetFactory()->createWidget($this, 'BaseEventTypeCActiveForm', array(
            'id' => 'clinical-create',
            'enableAjaxValidation' => false,
            'htmlOptions' => array('class' => 'sliding'),
        ));

        $this->renderElement($element, 'create', $form, null, array(
            'previous_parent_id' => $previous_id,
        ), false, true);
    }

    /**
     * Ajax method for viewing previous elements.
     *
     * @param int $element_type_id
     * @param int $patient_id
     *
     * @throws CHttpException
     */
    public function actionViewPreviousElements($element_type_id, $patient_id)
    {
        $element_type = ElementType::model()->findByPk($element_type_id);
        if (!$element_type) {
            throw new CHttpException(404, 'Unknown ElementType');
        }
        $this->patient = Patient::model()->findByPk($patient_id);
        if (!$this->patient) {
            throw new CHttpException(404, 'Unknown Patient');
        }

        // Clear script requirements as all the base css and js will already be on the page
        Yii::app()->assetManager->reset();

        $this->renderPartial(
            '_previous', array(
            'elements' => $this->getPrevious($element_type),
        ), false, true // Process output to deal with script requirements
        );
    }

    /**
     * Set the validation scenario for the element if necessary.
     *
     * @param $element
     */
    protected function setValidationScenarioForElement($element)
    {
        if ($child_types = $element->getElementType()->child_element_types) {
            $ct_cls_names = array();
            foreach ($child_types as $ct) {
                $ct_cls_names[] = $ct->class_name;
            }

            $has_children = false;
            foreach ($this->open_elements as $open) {
                $et = $open->getElementType();
                if ($et->isChild() && in_array($et->class_name, $ct_cls_names)) {
                    $has_children = true;
                    break;
                }
            }
            $element->scenario = $has_children ? 'formHasChildren' : 'formHasNoChildren';
        }
    }

    /**
     * Determines if this is a widget based element or not, and then sets the attributes from the data accordingly
     *
     * @param $element
     * @param $data
     * @param null $index
     */
    protected function setElementAttributesFromData($element, $data, $index = null)
    {
        $model_name = \CHtml::modelName($element);
        $el_data = is_null($index) ? $data[$model_name] : $data[$model_name][$index];

        if ($element->widgetClass) {
            $widget = $this->createWidget($element->widgetClass, array(
                'patient' => $this->patient,
                'element' => $element,
                'data' => $el_data,
                'mode' => \BaseEventElementWidget::$EVENT_EDIT_MODE
            ));
            $element->widget = $widget;
        } else {
            $element->attributes = Helper::convertNHS2MySQL($el_data);
            $this->setElementComplexAttributesFromData($element, $data, $index);
            $element->event = $this->event;
        }
    }

    /**
     * Looks for custom methods to set many to many data defined on elements. This is called prior to validation so should set values without actually
     * touching the database.
     *
     * The $data attribute will typically be the $_POST structure, but can be any appropriately structured array
     * The optional $index attribute is the counter for multiple elements of the same type that might exist in source data.
     *
     * The convention for the method name for the element setting is:
     *
     * setComplexAttributes_[element_class_name]($element, $data, $index)
     *
     * @param BaseEventTypeElement $element
     * @param array $data
     * @param int $index
     */
    protected function setElementComplexAttributesFromData($element, $data, $index = null)
    {
        $element_method = 'setComplexAttributes_' . Helper::getNSShortname($element);
        if (method_exists($this, $element_method)) {
            $this->$element_method($element, $data, $index);
        }
    }

    /**
     * Processes provided form data to create 1 or more elements of the provided type.
     *
     * @param ElementType $element_type
     * @param $data
     * @return array
     * @throws Exception
     */
    protected function getElementsForElementType(ElementType $element_type, $data)
    {
        $elements = array();
        $el_cls_name = $element_type->class_name;
        $f_key = CHtml::modelName($el_cls_name);

        $is_removed = !isset($data['element_removed'][$f_key]) || ( isset($data['element_removed'][$f_key]) &&  !$data['element_removed'][$f_key]);

        /**
         * Check if the element has data , but not the element removed flag
         * or if the element has removed flag set and if its not set to 0
         */
        if (isset($data[$f_key]) && $is_removed) {
            $keys = array_keys($data[$f_key]);
            if (is_array($data[$f_key][$keys[0]]) && !count(array_filter(array_keys($data[$f_key]), 'is_string'))) {
                // there is more than one element of this type
                $pk_field = $el_cls_name::model()->tableSchema->primaryKey;
                foreach ($data[$f_key] as $i => $attrs) {
                    if (!$this->event->isNewRecord && !isset($attrs[$pk_field])) {
                        throw new Exception('missing primary key field for multiple elements for editing an event');
                    }
                    if ($pk = @$attrs[$pk_field]) {
                        $element = $el_cls_name::model()->findByPk($pk);
                    } else {
                        $element = $element_type->getInstance();
                    }

                    $this->setElementAttributesFromData($element, $data, $i);
                    $elements[] = $element;
                }
            } else {
                if ($this->event->isNewRecord
                    || !$element = $el_cls_name::model()->find('event_id=?', array($this->event->id))) {
                    $element = $element_type->getInstance();
                }
                $this->setElementAttributesFromData($element, $data);
                $elements[] = $element;
            }
        }
        return $elements;
    }

    /**
     * Set the attributes of the given $elements from the given structured array.
     * Returns any validation errors that arise.
     *
     * @param array $data
     *
     * @throws Exception
     *
     * @return array $errors
     */
    protected function setAndValidateElementsFromData($data)
    {
        $errors = array();
        $elements = array();
        // only process data for elements that are part of the element type set for the controller event type
        foreach ($this->getAllElementTypes() as $element_type) {

            $from_data = $this->getElementsForElementType($element_type, $data);
            if (count($from_data) > 0) {
                $elements = array_merge($elements, $from_data);
            } elseif ($element_type->required) {
                $errors[$this->event_type->name][] = $element_type->name . ' is required';
                $elements[] = $element_type->getInstance();
            }
        }
        if (!count($elements)) {
            $errors[$this->event_type->name][] = 'Cannot create an event without at least one element';
        }

        // assign
        $this->open_elements = $elements;

        // validate
        foreach ($this->open_elements as $element) {
            $this->setValidationScenarioForElement($element);
            if (!$element->validate()) {
                $name = $element->getElementTypeName();
                foreach ($element->getErrors() as $errormsgs) {
                    foreach ($errormsgs as $error) {
                        $errors[$name][] = $error;
                    }
                }
            }
        }

        //event date and parent validation
        if (isset($data['Event']['event_date'])) {
            $event = $this->event;
            $event->event_date = Helper::convertNHS2MySQL($data['Event']['event_date']);
            if (isset($data['Event']['parent_id'])) {
                $event->parent_id = $data['Event']['parent_id'];
            }
            if (!$event->validate()) {
                foreach ($event->getErrors() as $errormsgs) {
                    foreach ($errormsgs as $error) {
                        $errors[$this->event_type->name][] = $error;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Generates the info text for controller event from the current elements, sets it on the event and saves it.
     */
    protected function updateEventInfo()
    {
        $info_text = '';
        foreach ($this->open_elements as $element) {
            if ($element->infotext) {
                $info_text .= $element->infotext;
            }
        }
        $this->event->info = $info_text;
        $this->event->save();
    }

    /**
     * Iterates through the open elements and calls the custom methods for saving complex data attributes to them.
     *
     * Custom method is of the name format saveComplexAttributes_[element_class_name]($element, $data, $index)
     *
     * @param $data
     */
    protected function saveEventComplexAttributesFromData($data)
    {
        $counter_by_cls = array();

        foreach ($this->open_elements as $element) {
            $el_cls_name = get_class($element);
            $element_method = 'saveComplexAttributes_' . Helper::getNSShortname($element);
            if (method_exists($this, $element_method)) {
                // there's custom behaviour for setting additional relations on this element class
                if (!isset($counter_by_cls[$el_cls_name])) {
                    $counter_by_cls[$el_cls_name] = 0;
                } else {
                    ++$counter_by_cls[$el_cls_name];
                }
                $this->$element_method($element, $data, $counter_by_cls[$el_cls_name]);
            }
        }
    }

    /**
     * Save the event for this controller - will create or update the event, create and update the elements, delete any
     * elements that are no longer required. Note that $data is provided for the purposes of any extensions to this
     * behaviour that might be required.
     *
     * @param $data
     *
     * @return bool
     *
     * @throws Exception
     */
    public function saveEvent($data)
    {
        if (!$this->event->isNewRecord) {
            // this is an edit, so need to work out what we are deleting
            $oe_ids = array();
            foreach ($this->open_elements as $o_e) {
                if ($o_e->id) {
                    if (isset($oe_ids[get_class($o_e)])) {
                        $oe_ids[get_class($o_e)][] = $o_e->id;
                    } else {
                        $oe_ids[get_class($o_e)] = array($o_e->id);
                    }
                }
            }
            // delete any elements that are no longer required for the event
            foreach ($this->event->getElements() as $curr_element) {
                if (!isset($oe_ids[get_class($curr_element)])
                    || !in_array($curr_element->id, $oe_ids[get_class($curr_element)])) {
                    // make sure that the element have a primary key (it tried to delete null elements before!)
                    if ($curr_element->getPrimaryKey() !== null) {
                        $curr_element->delete();
                    }
                }
            }
        } else {
            if (!$this->event->save()) {
                OELog::log("Failed to create new event for episode_id={$this->episode->id}, event_type_id=" . $this->event_type->id);
                throw new Exception('Unable to save event.');
            }
            OELog::log("Created new event for episode_id={$this->episode->id}, event_type_id=" . $this->event_type->id);
        }

        foreach ($this->open_elements as $element) {
            $element->event_id = $this->event->id;
            // No need to validate as it has already been validated and the event id was just generated.
            if (!$element->save(false)) {
                throw new Exception('Unable to save element ' . get_class($element) . '.');
            }
        }

        // ensure any complex data is saved to the elements
        $this->saveEventComplexAttributesFromData($data);

        // update the information attribute on the event
        $this->updateEventInfo();

        return true;
    }

    /**
     * Get the prefix name of this controller, used for path calculations for element views.
     *
     * @return string
     */
    protected function getControllerPrefix()
    {
        return strtolower(str_replace('Controller', '', Helper::getNSShortname($this)));
    }

    /**
     * Return the path alias for the module the element belongs to based on its namespace
     * (assumes elements exist in a namespace below the module namespace).
     *
     * @param BaseEventTypeElement $element
     *
     * @return string
     */
    public function getElementModulePathAlias(\BaseEventTypeElement $element)
    {
        $r = new ReflectionClass($element);

        if ($r->inNamespace()) {
            $ns_parts = explode('\\', $r->getNamespaceName());

            return implode('.', array_slice($ns_parts, 0, count($ns_parts) - 1));
        }

        return $this->modulePathAlias;
    }

    /**
     * Return the asset path for the given element (by interrogating namespace).
     *
     * @param BaseEventTypeElement $element
     *
     * @return string
     */
    public function getAssetPathForElement(\BaseEventTypeElement $element)
    {
        if ($alias = $this->getElementModulePathAlias($element)) {
            return Yii::app()->assetManager->getPublishedPathOfAlias($alias . '.assets');
        } else {
            return $this->assetPath;
        }
    }

    /**
     * calculate the alias dot notated path to an element view.
     *
     * @param BaseEventTypeElement $element
     *
     * @return string
     */
    protected function getElementViewPathAlias(\BaseEventTypeElement $element)
    {
        if ($alias = $this->getElementModulePathAlias($element)) {
            return $alias . '.views.' . $this->getControllerPrefix() . '.';
        }

        return '';
    }

    public function renderSidebar($default_view)
    {
        if ($this->show_element_sidebar && in_array($this->getActionType($this->action->id),
                array(static::ACTION_TYPE_CREATE, static::ACTION_TYPE_EDIT), true)) {
            $event_type_id = $this->event->attributes["event_type_id"];
            $event_type = EventType::model()->findByAttributes(array('id' => $event_type_id));
            $event_name =  preg_replace('/\s+/', '_', $event_type->name);
            $this->renderPartial('//patient/_patient_element_sidebar', array('event_name'=>$event_name));
        } else {
            parent::renderSidebar($default_view);
        }

    }

    public function renderIndexSearch()
    {
        if ($this->show_index_search && in_array($this->getActionType($this->action->id),
                array(static::ACTION_TYPE_CREATE, static::ACTION_TYPE_EDIT), true)) {

          $event_type_id = ($this->event->attributes["event_type_id"]);
          $event_type = EventType::model()->findByAttributes(array('id' => $event_type_id));
          $event_name = $event_type->name;
        }

    }


    /**
     * Extend the parent method to support inheritance of modules (and rendering the element views from the parent module).
     *
     * @param string $view
     * @param null $data
     * @param bool $return
     * @param bool $processOutput
     *
     * @return string
     */
    public function renderPartial($view, $data = null, $return = false, $processOutput = false)
    {
        if ($this->getViewFile($view) === false) {
            foreach ($this->getModule()->getModuleInheritanceList() as $mod) {
                // assuming that any inheritance maintains the controller name here.
                $view_path = implode('.', array($mod->id, 'views', $this->getControllerPrefix(), $view));
                if ($this->getViewFile($view_path)) {
                    $view = $view_path;
                    break;
                }
            }
        }

        return parent::renderPartial($view, $data, $return, $processOutput);
    }

    /**
     * Render the individual element based on the action provided. Note that view names
     * for the associated actions are set in the model.
     *
     * @param BaseEventTypeElement $element
     * @param string $action
     * @param BaseCActiveBaseEventTypeCActiveForm $form
     * @param array $data
     * @param array $view_data Data to be passed to the view.
     * @param bool $return Whether the rendering result should be returned instead of being displayed to end users.
     * @param bool $processOutput Whether the rendering result should be postprocessed using processOutput.
     *
     * @throws Exception
     */
    protected function renderElement($element, $action, $form, $data, $view_data = array(), $return = false, $processOutput = false)
    {

        if (strcasecmp($action, 'PDFPrint') == 0 || strcasecmp($action, 'saveCanvasImages') == 0) {
            $action = 'print';
        }
        if ($action == 'savePDFprint') {
            $action = 'print';
        }

        if($action === 'createImage') {
            $action = 'view';
        }

        // Get the view names from the model.
        $view = isset($element->{$action . '_view'})
            ? $element->{$action . '_view'}
            : $element->getDefaultView();
        $container_view = isset($element->{'container_' . $action . '_view'})
            ? $element->{'container_' . $action . '_view'}
            : $element->getDefaultContainerView();

        $use_container_view = ($element->useContainerView && $container_view);
        $view_data = array_merge(array(
            'element' => $element,
            'data' => $data,
            'form' => $form,
            'child' => $element->getElementType()->isChild(),
            'container_view' => $container_view,
        ), $view_data);

        // Render the view.
        ($use_container_view) && $this->beginContent($container_view, $view_data);
        if ($element->widgetClass) {
            // only wrap the element in a widget if it's not already in one
            $widget = $element->widget ?:
                $this->createWidget($element->widgetClass,
                    array(
                        'patient' => $this->patient,
                        'element' => $view_data['element'],
                        'data' => $view_data['data'],
                        'mode' => $this->getElementWidgetMode($action)
                    ));
            $widget->form = $view_data['form'];
            $this->renderPartial('//elements/widget_element', array('widget' => $widget),$return, $processOutput);
        } else {
            $this->renderPartial($this->getElementViewPathAlias($element).$view, $view_data, $return, $processOutput);
        }
        ($use_container_view) && $this->endContent();
    }

    /**
     * Render the open elements for the controller state.
     *
     * @param string $action
     * @param BaseCActiveBaseEventTypeCActiveForm $form
     * @param array $data
     *
     * @throws Exception
     */
    public function renderOpenElements($action, $form = null, $data = null)
    {
        $this->renderTiledElements($this->getElements($action), $action, $form, $data);
    }

    /**
     * @param $elements
     * @param $action
     * @param null $form
     * @param null $data
     * @throws CException
     * @throws Exception
     */
    public function renderTiledElements($elements, $action, $form = null, $data = null)
    {
        $element_count = count($elements);
        if($element_count < 1)return;
        $rows = array(array());
        foreach ($elements as $element) {
            if ($element->widgetClass) {
                $widget = $this->createWidget($element->widgetClass, array(
                    'patient' => $this->patient,
                    'element' => $element,
                    'data' => $data,
                    'mode' => $this->getElementWidgetMode($action)
                ));
                $element->widget = $widget;
                $element->widget->renderWarnings();
            }
        }
        //Find the groupings
        for ($element_index = 0, $tile_index = 0, $row_index = 0;
             $element_index < $element_count;
             $element_index++)
        {
            $element = $elements[$element_index];

            //if the tile size can't be determined assume a full row
            $sizeOfTile = $element->getTileSize($action) ?: $this->element_tiles_wide;
            if($tile_index + $sizeOfTile > $this->element_tiles_wide){
                $tile_index = 0;
                $rows[++$row_index] = array();
            }
            $rows[$row_index][] = $element;
            $tile_index += $sizeOfTile;
        }

        foreach ($rows as $row){
            if(count($row) > 1||($action=='view'&&$row[0]->getTileSize($action))){
                $this->beginWidget('TiledEventElementWidget');
                $this->renderElements($row, $action, $form, $data);
                $this->endWidget();
            } else {
                $this->renderElements($row, $action, $form, $data);
            }
        }

    }

    /**
     * @param $elements
     * @param $action
     * @param null $form
     * @param null $data
     * @throws Exception
     */
    public function renderElements($elements, $action, $form = null, $data = null)
    {
        if(count($elements) < 1){return;}
        foreach ($elements as $element){
            $this->renderElement($element, $action, $form, $data);
        }
    }

    /**
     * Render an optional element.
     *
     * @param BaseEventTypeElement $element
     * @param string $action
     * @param BaseCActiveBaseEventTypeCActiveForm $form
     * @param array $data
     *
     * @throws Exception
     */
    protected function renderOptionalElement($element, $action, $form, $data)
    {
        $el_view = $this->getElementViewPathAlias($element) . '_optional_' . $element->getDefaultView();
        $view = $this->getViewFile($el_view)
            ? $el_view
            : $this->getElementViewPathAlias($element) . '_optional_element';

        $this->renderPartial($view, array(
            'element' => $element,
            'data' => $data,
            'form' => $form,
        ), false, false);
    }

    /**
     * Render the open elements that are children of the given parent element type.
     *
     * @param BaseEventTypeElement $parent_element
     * @param string $action
     * @param BaseCActiveBaseEventTypeCActiveForm $form
     * @param array $data
     *
     * @throws Exception
     */
    public function renderChildOpenElements($parent_element, $action, $form = null, $data = null)
    {
        foreach ($this->getChildElements($parent_element->getElementType()) as $element) {
            $this->renderElement($element, $action, $form, $data);
        }
    }

    /**
     * Render the optional elements for the controller state.
     *
     * @param string $action
     * @param bool $form
     * @param bool $data
     */
    public function renderOptionalElements($action, $form = null, $data = null)
    {
        foreach ($this->getOptionalElements() as $element) {
            $this->renderOptionalElement($element, $action, $form, $data);
        }
    }

    /**
     * Render the optional child elements for the given parent element type.
     *
     * @param BaseEventTypeElement $parent_element
     * @param string $action
     * @param BaseCActiveBaseEventTypeCActiveForm $form
     * @param array $data
     *
     * @throws Exception
     */
    public function renderChildOptionalElements($parent_element, $action, $form = null, $data = null)
    {
        foreach ($this->getChildOptionalElements($parent_element->getElementType()) as $element) {
            $this->renderOptionalElement($element, $action, $form, $data);
        }
    }

    /**
     * Get all the episodes for the current patient.
     *
     * @return array
     */
    public function getEpisodes()
    {
        if (empty($this->episodes)) {
            $this->episodes = array(
                'ordered_episodes' => $this->patient->getOrderedEpisodes(),
                'legacyepisodes' => $this->patient->legacyepisodes,
                'supportserviceepisodes' => $this->patient->supportserviceepisodes,
            );
        }

        return $this->episodes;
    }

    /**
     * Get the current episode for the firm and patient.
     *
     * @return Episode
     */
    public function getEpisode()
    {
        return Episode::model()->getCurrentEpisodeByFirm($this->patient->id, $this->firm);
    }

    /**
     * Render the given errors with the standard template.
     *
     * @param $errors
     * @param bool $bottom
     */
    public function displayErrors($errors, $bottom = false)
    {
        $this->renderPartial('//elements/form_errors', array(
            'errors' => $errors,
            'bottom' => $bottom,
            'elements' => $this->open_elements,
        ));
    }

    /**
     * Print action.
     *
     * @param int $id event id
     */
    public function actionPrint($id)
    {
        $this->printInit($id);
        $this->printHTML($id, $this->open_elements);
    }

    /**
     * returns a suffix for PDF rendering
     */
    private function getPDFPrintSuffix()
    {
        if (method_exists($this, "getSession")) {
            $this->pdf_print_suffix .= Yii::app()->user->id . '_' . rand();
        } else {
            $this->pdf_print_suffix .= getmypid() . rand();
        }
    }

    /**
     *
     * Prepares the PDF print action by setting object variables
     *
     * @param $id
     * @param $inject_autoprint_js
     * @return null
     * @throws CHttpException
     * @throws Exception
     */
    public function setPDFprintData($id, $inject_autoprint_js)
    {
        if (!isset($id)) {
            throw new CHttpException(400, 'No ID provided');
        }

        if (!$this->event = Event::model()->findByPk($id)) {
            throw new Exception("Method not found: " . $id);
        }

        $this->attachment_print_title = Yii::app()->request->getParam('attachment_print_title', null);

        $this->event->lock();

        $this->getPDFPrintSuffix();

        if (!$this->event->hasPDF($this->pdf_print_suffix) || @$_GET['html']) {
            if (!$this->pdf_print_html) {
                ob_start();
                $this->actionPrint($this->event->id);
                $this->pdf_print_html = ob_get_contents();
                ob_end_clean();
            }
            $this->renderAndSavePDFFromHtml($this->pdf_print_html, $inject_autoprint_js);
        }

        $this->event->unlock();

        return $this->pdf_print_suffix;
    }

    /**
     * Render and save a PDF file from the input HTML string
     *
     * @param $html
     * @param $inject_autoprint_js
     * @return null
     */
    public function renderAndSavePDFFromHtml($html, $inject_autoprint_js)
    {
        $this->getPDFPrintSuffix();

        $wk = new WKHtmlToPDF();

        $wk->setCanvasImagePath($this->event->imageDirectory);
        $wk->setDocuments($this->pdf_print_documents);
        $wk->setDocref($this->event->docref);
        $wk->setPatient($this->event->episode->patient);
        $wk->setBarcode($this->event->barcodeHTML);

        foreach (array('left', 'middle', 'right') as $section) {
            if (isset(Yii::app()->params['wkhtmltopdf_footer_'.$section.'_'.$this->event_type->class_name])) {
                $setMethod = 'set'.ucfirst($section);
                $wk->$setMethod(Yii::app()->params['wkhtmltopdf_footer_'.$section.'_'.$this->event_type->class_name]);
            }
        }

        foreach (array('top', 'bottom', 'left', 'right') as $margin) {
            if (isset(Yii::app()->params['wkhtmltopdf_' . $margin.'_margin_'.$this->event_type->class_name])) {
                $setMethod = 'setMargin'.ucfirst($margin);
                $wk->$setMethod(Yii::app()->params['wkhtmltopdf_' . $margin.'_margin_'.$this->event_type->class_name]);
            }
        }

        foreach (PDFFooterTag::model()->findAll('event_type_id = ?', array($this->event_type->id)) as $pdf_footer_tag) {
            if ($api = Yii::app()->moduleAPI->get($this->event_type->class_name)) {
                $wk->setCustomTag($pdf_footer_tag->tag_name, $api->{$pdf_footer_tag->method}($this->event->id));
            }
        }

        $wk->generatePDF($this->event->imageDirectory, 'event', $this->pdf_print_suffix, $html, (boolean)@$_GET['html'], $inject_autoprint_js);

        return $this->pdf_print_suffix;
    }

    /**
     * Saves a print to PDF as a ProtectedFile object and file
     *
     * @param $id
     * @return array
     */
    public function actionSavePDFprint($id)
    {

        $auto_print = Yii::app()->request->getParam('auto_print', true);
        $inject_autoprint_js = $auto_print == "0" ? false : $auto_print;

        $pdf_route = $this->setPDFprintData($id, $inject_autoprint_js);
        $pf = ProtectedFile::createFromFile($this->event->imageDirectory . '/event_' . $pdf_route . '.pdf');
        if ($pf->save()) {
            $result = array(
                'success' => 1,
                'file_id' => $pf->id,
            );

            if (!isset($_GET['ajax'])) {
                $result['name'] = $pf->name;
                $result['mime'] = $pf->mimetype;
                $result['path'] = $pf->getPath();

                return $result;
            }

        } else {
            $result = array(
                'success' => 0,
                'message' => "couldn't save file object" . print_r($pf->getErrors(), true)
            );
        }

        $this->renderJSON($result);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function actionPDFPrint($id)
    {
        $auto_print = Yii::app()->request->getParam('auto_print', true);
        $inject_autoprint_js = $auto_print == "0" ? false : $auto_print;

        $pdf_route = $this->setPDFprintData($id, $inject_autoprint_js);
        if (@$_GET['html']) {
            return Yii::app()->end();
        }

        $pdf = $this->event->getPDF($pdf_route);


        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($pdf));

        readfile($pdf);
        @unlink($pdf);
    }

    /**
     * Initialise print action.
     *
     * @param int $id event id
     *
     * @throws CHttpException
     * @TODO: standardise printInit function as per init naming convention
     */
    protected function printInit($id)
    {
        if (!$this->event = Event::model()->findByPk($id)) {
            throw new CHttpException(403, 'Invalid event id.');
        }
        $this->patient = $this->event->episode->patient;
        $this->episode = $this->event->episode;
        $this->site = Site::model()->findByPk(Yii::app()->session['selected_site_id']);
        $this->setOpenElementsFromCurrentEvent('print');
    }

    /**
     * Render HTML print layout.
     *
     * @param int $id event id
     * @param BaseEventTypeElement[] $elements
     * @param string $template
     */
    protected function printHTML($id, $elements, $template = 'print')
    {
        $this->layout = '//layouts/print';
        $this->render($template, array(
            'elements' => $elements,
            'eventId' => $id
        ));
    }

    public function printHTMLCopy($id, $elements, $template = 'print')
    {
        $this->layout = '//layouts/printCopy';
        $result = $this->render($template, array(
            'elements' => $elements,
            'eventId' => $id,
        ), true);

        echo $result;
    }

    /**
     * Log print action.
     *
     * @param int $id event id
     * @param bool $pdf
     */
    protected function printLog($id, $pdf)
    {
        $this->logActivity("printed event (pdf=$pdf)");
        $this->event->audit('event', (strpos($this->pdf_print_suffix, 'all') === 0 ? 'print all' : 'print'), false);
    }

    /**
     * Run this function after soft delete happened
     *
     * @param $event
     * @return bool
     */
    public function afterSoftDelete($event)
    {
        return true;
    }

    /**
     * Delete the event given by $id. Performs the soft delete action if it's been confirmed by $_POST.
     *
     * @param $id
     *
     * @throws CHttpException
     * @throws Exception
     */
    public function actionDelete($id)
    {
        if (isset($_POST['et_canceldelete'])) {
            return $this->redirect(array('/' . $this->event_type->class_name . '/default/view/' . $id));
        }

        if (!empty($_POST)) {

            if (Yii::app()->request->getPost('delete_reason', '') === '') {
                $errors = array('Reason for deletion' => array('Please enter a reason for deleting this event'));
            } else {
                $transaction = Yii::app()->db->beginTransaction();
                try {
                    $this->event->softDelete(Yii::app()->request->getPost('delete_reason', ''));

                    $this->event->audit('event', 'delete', false);

                    if (Event::model()->count('episode_id=?', array($this->event->episode_id)) == 0) {
                        $this->event->episode->deleted = 1;
                        if (!$this->event->episode->save()) {
                            throw new Exception('Unable to save episode: ' . print_r($this->event->episode->getErrors(), true));
                        }

                        $this->event->episode->audit('episode', 'delete', false);

                        $transaction->commit();

                        if (!$this->dont_redirect) {
                            $this->redirect(array('/patient/episodes/' . $this->event->episode->patient->id));
                        } else {
                            return true;
                        }
                    }

                    Yii::app()->user->setFlash('success', 'An event was deleted, please ensure the episode status is still correct.');
                    $transaction->commit();

                    if (!$this->dont_redirect) {
                        $this->redirect(array('/patient/episode/' . $this->event->episode_id));
                    }

                    return true;
                } catch (Exception $e) {
                    $transaction->rollback();
                    throw $e;
                }
            }
        }

        $this->title = 'Delete ' . $this->event_type->name;

        $this->event_tabs = array(
            array(
                'label' => 'View',
                'active' => true,
            ),
        );
        if ($this->editable) {
            $this->event_tabs[] = array(
                'label' => 'Edit',
                'href' => Yii::app()->createUrl($this->event->eventType->class_name . '/default/update/' . $this->event->id),
            );
        }

        $this->processJsVars();

        $episodes = $this->getEpisodes();
        $viewData = array_merge(array(
            'eventId' => $id,
            'errors' => isset($errors) ? $errors : null
        ), $episodes);

        $this->render('delete', $viewData);
    }

    /**
     * Called after event (and elements) has been updated.
     *
     * @param Event $event
     */
    protected function afterUpdateElements($event)
    {
        $this->updateUniqueCode($event);
    }

    /**
     * Called after event (and elements) have been created.
     *
     * @param Event $event
     */
    protected function afterCreateElements($event)
    {
        $this->updateUniqueCode($event);
    }

    /**
     * Update Unique code for the event associated the specific procedures.
     */
    private function updateUniqueCode($event)
    {
        foreach ($this->unique_code_elements as $unique) {
            if ($event->eventType->class_name === $unique['event']) {
                foreach ($event->getElements() as $element) {
                    if (in_array(Helper::getNSShortname($element), $unique['element'])) {
                        $event_unique_code = UniqueCodeMapping::model()->findAllByAttributes(array('event_id' => $event->id));
                        if (!$event_unique_code) {
                            $this->createNewUniqueCodeMapping($event->id, null);
                        }
                    }
                }
            }
        }
    }


    /**
     * set base js vars for use in the standard scripts for the controller.
     */
    public function processJsVars()
    {
        if ($this->patient) {
            $this->jsVars['OE_patient_id'] = $this->patient->id;
            $this->jsVars['OE_patient_hosnum'] = $this->patient->hos_num;
        }
        if ($this->event) {
            $this->jsVars['OE_event_id'] = $this->event->id;

            if (Yii::app()->params['event_print_method'] == 'pdf') {
                $this->jsVars['OE_print_url'] = Yii::app()->createUrl($this->getModule()->name . '/default/PDFprint/' . $this->event->id);
            } else {
                $this->jsVars['OE_print_url'] = Yii::app()->createUrl($this->getModule()->name . '/default/print/' . $this->event->id);
            }
        }
        $this->jsVars['OE_asset_path'] = $this->assetPath;
        $firm = Firm::model()->findByPk(Yii::app()->session['selected_firm_id']);
        $subspecialty_id = $firm->serviceSubspecialtyAssignment ? $firm->serviceSubspecialtyAssignment->subspecialty_id : null;
        $this->jsVars['OE_subspecialty_id'] = $subspecialty_id;

        parent::processJsVars();
    }

    /**
     * Sets the the css state.
     */
    protected function initActionRequestDeletion()
    {
        $this->moduleStateCssClass = 'view';

        $this->initWithEventId(@$_GET['id']);
    }

    /**
     * Action to process delete requests for an event.
     *
     * @param $id
     *
     * @return bool|void
     *
     * @throws CHttpException
     */
    public function actionRequestDeletion($id)
    {
        if (!$this->event = Event::model()->findByPk($id)) {
            throw new CHttpException(403, 'Invalid event id.');
        }

        if (isset($_POST['et_canceldelete'])) {
            return $this->redirect(array('/' . $this->event->eventType->class_name . '/default/view/' . $id));
        }

        $this->patient = $this->event->episode->patient;

        $errors = array();

        if (!empty($_POST)) {
            if (!@$_POST['delete_reason']) {
                $errors = array('Reason' => array('Please enter a reason for deleting this event'));
            } else {
                $this->event->requestDeletion($_POST['delete_reason']);

                if (Yii::app()->params['admin_email']) {
                    mail(Yii::app()->params['admin_email'], 'Request to delete an event', 'A request to delete an event has been submitted.  Please log in to the admin system to review the request.', 'From: OpenEyes');
                }

                Yii::app()->user->setFlash('success', 'Your request to delete this event has been submitted.');

                header('Location: ' . Yii::app()->createUrl('/' . $this->event_type->class_name . '/default/view/' . $this->event->id));

                return true;
            }
        }

        $this->title = 'Delete ' . $this->event_type->name;
        $this->event_tabs = array(array(
            'label' => 'View',
            'active' => true,
        ));

        $this->render('request_delete', array(
            'errors' => $errors,
        ));
    }

    /**
     * Get open element by class name.
     *
     * @param string $class_name
     *
     * @return object
     */
    public function getOpenElementByClassName($class_name)
    {
        if (!empty($this->open_elements)) {
            foreach ($this->open_elements as $element) {
                if (CHtml::modelName($element) == $class_name) {
                    return $element;
                }
            }
        }

        return;
    }

    /**
     * Set the open elements (for unit testing).
     *
     * @param array $open_elements
     */
    public function setOpenElements($open_elements)
    {
        $this->open_elements = $open_elements;
    }

    public function actionSaveCanvasImages($id)
    {
        $this->event = Event::model()->findByPk($id);
        if (!$this->event) {
            throw new Exception("Event not found: $id");
        }

        if (strtotime($this->event->last_modified_date) != @$_POST['last_modified_date']) {
            echo 'outofdate';

            return;
        }

        $this->event->lock();

        if (!file_exists($this->event->imageDirectory)) {
            if (!@mkdir($this->event->imageDirectory, 0755, true)) {
                throw new Exception("Unable to create directory: $event->imageDirectory");
            }
        }

        if (!empty($_POST['canvas'])) {
            foreach ($_POST['canvas'] as $drawingName => $blob) {
                if (!file_exists($this->event->imageDirectory . "/$drawingName.png")) {
                    if (!@file_put_contents(
                            $this->event->imageDirectory . "/$drawingName.png",
                            base64_decode(preg_replace('/^data\:image\/png;base64,/', '', $blob))
                        )
                    ){
                        throw new Exception("Failed to write to {$this->event->imageDirectory}/$drawingName.png: check permissions.");
                    }
                }
            }
        }

        // Regenerate the EventImage in the background
        EventImageManager::actionGenerateImage($this->event);

        /*
         * TODO: need to check with all events why this was here!!!
        ob_start();
        $this->actionPrint($id, false);
        $html = ob_get_contents();
        ob_end_clean();

        $event->unlock();

        $this->printLog($id, false);

        // Verify we have all the images by detecting eyedraw canvas elements in the page.
        // If we don't, the "outofdate" response will trigger a page-refresh so we can re-send the canvas elements to the
        // server as PNGs.

        if (preg_match('/<canvas.*?class="ed-canvas-display"/is', $html)) {
            echo 'outofdate';

            return;
        }
        */

        echo 'ok';
    }

    public function readInEventImageSettings(){
        $this->event = Event::model()->findByPk($_GET['id']);
        if (!isset($this->event) || !isset($this->event->eventType)){return;}

        $event_params = array();
        if (array_key_exists('event_specific', Yii::app()->params['lightning_viewer']))
        {
            $lightning_params = Yii::app()->params['lightning_viewer']['event_specific'];
            if (array_key_exists($this->event->eventType->name, $lightning_params)){
                $event_params = $lightning_params[$this->event->eventType->name];
            }
        }

        if (!isset($event_params)){return;};

        foreach ($event_params as $key => $value){
            $this->{$key} = $value;
        }
    }

    public function actionEventImage()
    {
        if (!$event = Event::model()->findByPk(@$_GET['event_id'])) {
            throw new Exception('Event not found: ' . @$_GET['event_id']);
        }

        if (!$event->hasEventImage(@$_GET['image_name'])) {
            throw new Exception("Event $event->id image missing: " . @$_GET['image_name']);
        }

        $path = $event->getImagePath(@$_GET['image_name']);

        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    /**
     * @throws CException
     */
    protected function persistPcrRisk()
    {
        $pcrRisk = new \PcrRisk();
        $pcrData = Yii::app()->request->getPost('PcrRisk', array());
        foreach ($pcrData as $side => $sideData) {
            $pcrRisk->persist($side, $this->patient, $sideData);
        }
    }



    /**
     * Gets a value indicating whether this event has any extra information to display in the title
     * This function will always return false, but can be overridden to return true
     * iF it is, then getExtraTitleInfo() should also be overridden.
     *
     * @return bool
     */
    public function hasExtraTitleInfo()
    {
        return false;
    }

    /**
     * Gets the extra info to be displayed in the title of this event
     * Should only be overridden if hasExtraTitleInfo() has also been overridden
     *
     * @return string HTML to display next to the title
     * @throws BadMethodCallException thrown if the method hasn't been overridden
     */
    public function getExtraTitleInfo()
    {
        throw new BadMethodCallException('getExtraTitleInfo() should have been overridden by ' . get_class($this));
    }

    protected function updateHotlistItem(Patient $patient)
    {
        $user = Yii::app()->user;
        $hotlistItem = UserHotlistItem::model()->find(
            'created_user_id = :user_id AND patient_id = :patient_id 
                       AND (DATE(last_modified_date) = :current_date OR is_open = 1)',
            array(':user_id' => $user->id, ':patient_id' => $patient->id, ':current_date' => date('Y-m-d')));

        if (!$hotlistItem) {
            $hotlistItem = new UserHotlistItem();
            $hotlistItem->patient_id = $patient->id;
        }

        $hotlistItem->is_open = 1;
        if (!$hotlistItem->save()) {
            throw new Exception('UserHotListItem failed validation ' . print_r($hotlistItem->errors, true));
        };
    }


    /**
     * Creates the preview image for the event with the given ID
     *
     * @param integer $id The ID of the event to image
     * @throws Exception
     */
    public function actionCreateImage($id)
    {
        $this->initActionView();
        // Stub an EventImage record so other threads don't try to create the same image
        $eventImage = $this->saveEventImage('GENERATING');

        $this->readInEventImageSettings();
        try {
            $content = $this->getEventAsHtml();

            $image = new WKHtmlToImage();
            $image->setCanvasImagePath($this->event->getImageDirectory());
            $image->generateImage($this->event->getImageDirectory(), 'preview', '', $content,
                ['width' => Yii::app()->params['lightning_viewer']['image_width'],
                 'viewport_width' => Yii::app()->params['lightning_viewer']['viewport_width']]);

            $input_path = $this->event->getImagePath('preview');
            $output_path = $this->event->getImagePath('preview', '.jpg');
            $imagick = new Imagick($input_path);
            $imagick->writeImage($output_path);

            $this->saveEventImage('CREATED', ['image_path' => $output_path]);

            if (!Yii::app()->params['lightning_viewer']['keep_temp_files']) {
                $image->deleteFile($input_path);
                $image->deleteFile($output_path);
            }

        } catch (Exception $ex) {
            // Store an error entry,so that no attempts are made to generate the image again until the errors are fixed
            $this->saveEventImage('FAILED', ['message' => (string)$ex]);
            throw $ex;
        }
    }

    /**
     * Scales down the input image if it is larger than the maximum width
     *
     * @param Imagick $imagick
     */
    protected function scaleImageForThumbnail($imagick)
    {
        $width = $this->image_width ?: 800;
        if ($width < $imagick->getImageWidth()) {
            $height = $width * $imagick->getImageHeight() / $imagick->getImageWidth();
            $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            return true;
        }

        return false;
    }

    /**
     * Renders the event and returns the resullting HTML
     *
     * @return string The output HTML
     */
    protected function getEventAsHtml()
    {
        ProfileController::changeDisplayTheme(Yii::app()->user->id, 'dark');
        ob_start();

        $this->setOpenElementsFromCurrentEvent('view');

        $viewData = array_merge(array(
            'elements' => $this->open_elements,
            'eventId' => $this->event->id,
        ), $this->extraViewProperties);

        $this->layout = '//layouts/event_image';
        $this->render('image', $viewData);

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Gets the image path that will be used to store a temporary preview image
     *
     * @param array $options Additional options, including the page number, and eye
     * @param string $extension The file extension of the path (defaults to '.png')
     * @return string The path of the image
     */
    public function getPreviewImagePath(array $options = array(), $extension = '.png')
    {
        $filename = 'preview';

        if (isset($options['eye'])) {
            $filename .= '-' . $options['eye'];
        }

        if (isset($options['page'])) {
            $filename .= '-' . $options['page'];
        }

        $path = $this->event->getImagePath($filename, $extension);

        if (!file_exists(dirname($path))) {
            mkdir(dirname($path));
        }

        return $path;
    }

    /**
     * Removes all preview images for this event
     */
    protected function removeEventImages()
    {
        EventImage::model()->deleteAll('event_id = ?', $this->event->id);
    }

    /**
     * Saves a new EventImage record with the given status, and other options
     * Without additional options, only a stub will be created
     *
     * @param 0string $status The name of the status to use. Can be one of 'GENERATING', 'NOT_CREATED', 'FAILED' or 'COMPLETE'
     * @param array $options Additional options, including the page, eye_id, image_path, and error message
     * @return EventImage The created EventImage record
     * @throws Exception
     */
    protected function saveEventImage($status, array $options = [])
    {
        $criteria = new CDbCriteria();
        $criteria->compare('event_id', $this->event->id);
        if (isset($options['page'])) {
            $criteria->addCondition('(page IS NULL OR page = :page)');
            $criteria->params[':page'] = $options['page'];
        }

        if (isset($options['eye_id'])) {
            $criteria->addCondition('(eye_id IS NULL OR eye_id = :eye_id)');
            $criteria->params[':eye_id'] = $options['eye_id'];
        }

        $eventImage = EventImage::model()->find($criteria) ?: new EventImage();
        $eventImage->event_id = $this->event->id;
        if(isset($options['image_path'])) {
            $eventImage->image_data = file_get_contents($options['image_path']);

            if (!Yii::app()->params['lightning_viewer']['keep_temp_files']) {
                @unlink($options['image_path']);
            }
        }

        $eventImage->eye_id = @$options['eye_id'];
        $eventImage->page = @$options['page'];
        $eventImage->status_id = EventImageStatus::model()->find('name = ?', array($status))->id;

        if(isset($options['message'])) {
            $eventImage->message = $options['message'];
        }

        if (!$eventImage->save()) {
            throw new Exception('Could not save event image: ' . print_r($eventImage->getErrors(), true));
        }

        return $eventImage;
    }

    /**
     * Creates preview images for all pages of the given PDF file
     *
     * @param string $pdf_path The path for the PDF file
     * @param int|null $eye The eye ID the PDF is for
     * @throws Exception
     */
    protected function createPdfPreviewImages($pdf_path, $eye = null)
    {
        $pdf_imagick = new Imagick();
        $pdf_imagick->readImage($pdf_path);
        $pdf_imagick->setImageFormat('png');
        $original_width = $pdf_imagick->getImageGeometry()['width'];
        if ($this->image_width != 0 && $original_width != $this->image_width){
            $original_res = $pdf_imagick->getImageResolution()['x'];
            $new_res = $original_res * ($this->image_width / $original_width);

            $pdf_imagick = new Imagick();
            $pdf_imagick->setResolution($new_res,$new_res);
            $pdf_imagick->readImage($pdf_path);
            $pdf_imagick->setImageCompressionQuality($this->compression_quality);
        }

        $output_path = $this->getPreviewImagePath(['eye' => $eye]);
        if (!$pdf_imagick->writeImages($output_path, false)) {
            throw new Exception('An error occurred when attempting to convert eh PDF file to images');
        }

        // Try to save the PDF as though it only has one page
        $result = $this->savePdfPreviewAsEventImage(null, $eye);
        if (!$result) {
            // If nothing was saved, then it has multiple pages
            for ($page = 0; ; ++$page) {
                $result = $this->savePdfPreviewAsEventImage($page, $eye);
                if(!$result) {
                    break;
                }
            }
        }
    }

    /**
     * Attempts to create the EventImage record for the given page
     *
     * @param int|null $page The page number of the PDF
     * @param int|null $eye The eye side if it exists
     * @return bool True if the page exists, otherwise false
     * @throws ImagickException Thrown if the layers can't be merged
     * @throws Exception
     */
    protected function savePdfPreviewAsEventImage($page, $eye)
    {
        $pagePreviewPath = $this->getPreviewImagePath(['page' => $page, 'eye' => $eye]);
        if (!file_exists($pagePreviewPath)) {
            return false;
        }

        $imagickPage = new Imagick();
        $imagickPage->readImage($pagePreviewPath);

        // Sometimes the PDf has a transparent background, which should be replaced with white
        $this->whiteOutImageImagickBackground($imagickPage);

        $imagickPage->writeImage($pagePreviewPath);
        $this->saveEventImage('CREATED', ['image_path' => $pagePreviewPath, 'page' => $page, 'eye' => $eye]);

        if(!Yii::app()->params['lightning_viewer']['keep_temp_files']) {
            @unlink($pagePreviewPath);
        }

        return true;
    }

    /**
     * Makes transparent imagick images have a white background
     *
     * @param $imagick Imagick
     * @throws Exception
     */
    protected function whiteOutImageImagickBackground($imagick){
        if ($imagick->getImageAlphaChannel()) {
            // 11 Is the alphachannel_flatten value , a hack until all machines use the same imagick version
            $imagick->setImageAlphaChannel(defined('Imagick::ALPHACHANNEL_FLATTEN') ? Imagick::ALPHACHANNEL_FLATTEN : 11);
            $imagick->setImageBackgroundColor('white');
            $imagick->mergeImageLayers(imagick::LAYERMETHOD_FLATTEN);
        }
    }
}
