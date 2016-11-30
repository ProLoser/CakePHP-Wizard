<?php
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('Controller', 'Controller');
App::uses('Model', 'Model');
App::uses('Router', 'Routing');
App::uses('WizardComponent', 'Wizard.Controller/Component');

class WizardUserMock extends Model {

	public $useTable = false;

	public $validate = array(
		'gender' => array(
			'inList' => array(
				'rule' => array('inList', array('male', 'female')),
			),
		),
	);

}

/**
 * AuthTestController class
 *
 * @package       Wizard.Test.Case.Controller.Component
 */
class WizardTestController extends Controller {

	public $autoRender = false;

	public $uses = array('WizardUserMock');

	public $components = array(
		'Session',
		'Wizard.Wizard' => array(
			'autoValidate' => true,
			'steps' => array(
				'step1',
				'step2',
				'gender', // This step is autovalidated.
				array(
					'male' => array('step3', 'step4'),
					'female' => array('step4', 'step5'),
					'unknown' => 'step6',
				),
				'confirmation',
			),
		),
	);

	public function wizard($step = null) {
		$this->Wizard->process($step);
	}

	public function _processStep1() {
		if (!empty($this->request->data)) {
			return true;
		}
		return false;
	}

	public function _processStep2() {
		if (!empty($this->request->data)) {
			return true;
		}
		return false;
	}

	public function _processStep3() {
		if (!empty($this->request->data)) {
			return true;
		}
		return false;
	}

	public function _processStep4() {
		if (!empty($this->request->data)) {
			return true;
		}
		return false;
	}

	public function _processStep5() {
		if (!empty($this->request->data)) {
			return true;
		}
		return false;
	}

	public function _processConfirmation() {
		return true;
	}

	protected function _afterComplete() {
	}

	public function redirect($url = null, $status = null, $exit = true) {
		// Do not allow redirect() to exit in unit tests.
		return parent::redirect($url, $status, false);
	}
}

/**
 * WizardComponentTest class
 *
 * @property WizardComponent $Wizard
 * @package       Wizard.Test.Case.Controller.Component
 */
class WizardComponentTest extends CakeTestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$CakeRequest = new CakeRequest(null, false);
		$CakeResponse = $this->getMock('CakeResponse', array('send'));
		$this->Controller = new WizardTestController($CakeRequest, $CakeResponse);
		$ComponentCollection = new ComponentCollection();
		$ComponentCollection->init($this->Controller);
		$this->Controller->Components->init($this->Controller);
		$this->Wizard = $this->Controller->Wizard;
		$this->Wizard->initialize($this->Controller);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		$this->Wizard->Session->delete('Wizard');
		unset($this->Controller, $this->Wizard);
	}

/**
 * Test WizardComponent::initialize().
 *
 * @return void
 */
	public function testInitialize() {
		$this->assertTrue($this->Wizard->controller instanceof WizardTestController);
	}

	public function testConfig() {
		$steps = array('account', 'review');
		$result = $this->Wizard->config('steps', $steps);
		$this->assertEquals($steps, $result);

		$configSteps = $this->Wizard->Session->read('Wizard.config.steps');
		$this->assertEquals($steps, $configSteps);

		$result = $this->Wizard->config('steps');
		$this->assertEquals($steps, $result);
	}

	public function testBranch() {
		$this->Wizard->branch('female');
		$expectedBranches = array(
			'WizardTest' => array(
				'female' => 'branch',
			),
		);
		$sessionBranches = $this->Wizard->Session->read('Wizard.branches');
		$this->assertEquals($expectedBranches, $sessionBranches);
	}

	public function testBranchSkip() {
		$this->Wizard->branch('female', true);
		$expectedBranches = array(
			'WizardTest' => array(
				'female' => 'skip',
			),
		);
		$sessionBranches = $this->Wizard->Session->read('Wizard.branches');
		$this->assertEquals($expectedBranches, $sessionBranches);
	}

	public function testBranchOverwrite() {
		$this->Wizard->branch('male');
		$this->Wizard->branch('female');
		$expectedBranches = array(
			'WizardTest' => array(
				'male' => 'branch',
				'female' => 'branch',
			),
		);
		$sessionBranches = $this->Wizard->Session->read('Wizard.branches');
		$this->assertEquals($expectedBranches, $sessionBranches);

		$this->Wizard->branch('male', true);
		$expectedBranches = array(
			'WizardTest' => array(
				'male' => 'skip',
				'female' => 'branch',
			),
		);
		$sessionBranches = $this->Wizard->Session->read('Wizard.branches');
		$this->assertEquals($expectedBranches, $sessionBranches);
	}

	public function testStartup() {
		$configAction = $this->Wizard->Session->read('Wizard.config.action');
		$this->assertEmpty($configAction);
		$configSteps = $this->Wizard->Session->read('Wizard.config.steps');
		$this->assertEmpty($configSteps);
		$this->assertEmpty($this->Wizard->controller->helpers);

		$this->Wizard->startup($this->Controller);

		$expectedAction = 'wizard';
		$resultAction = $this->Wizard->Session->read('Wizard.config.action');
		$this->assertEquals($expectedAction, $resultAction);
		$expectedSteps = array(
			'step1',
			'step2',
			'gender',
			'step3',
			'step4',
			'confirmation',
		);
		$resultSteps = $this->Wizard->Session->read('Wizard.config.steps');
		$this->assertEquals($expectedSteps, $resultSteps);
		$this->assertEquals($expectedSteps, $this->Wizard->steps);
		$expectedHelpers = array(
			'Wizard.Wizard',
		);
		$this->assertEquals($expectedHelpers, $this->Wizard->controller->helpers);
	}

	public function testStartupSkipBranch() {
		$configSteps = $this->Wizard->Session->read('Wizard.config.steps');
		$this->assertEmpty($configSteps);

		$this->Wizard->branch('male', true);
		$this->Wizard->branch('female', true);
		$this->Wizard->startup($this->Controller);

		$expectedSteps = array(
			'step1',
			'step2',
			'gender',
			'step6',
			'confirmation',
		);
		$resultSteps = $this->Wizard->Session->read('Wizard.config.steps');
		$this->assertEquals($expectedSteps, $resultSteps);
		$this->assertEquals($expectedSteps, $this->Wizard->steps);
	}

	public function testStartupBranch() {
		$configSteps = $this->Wizard->Session->read('Wizard.config.steps');
		$this->assertEmpty($configSteps);

		$this->Wizard->branch('female');
		$this->Wizard->startup($this->Controller);

		$expectedSteps = array(
			'step1',
			'step2',
			'gender',
			'step4',
			'step5',
			'confirmation',
		);
		$resultSteps = $this->Wizard->Session->read('Wizard.config.steps');
		$this->assertEquals($expectedSteps, $resultSteps);
		$this->assertEquals($expectedSteps, $this->Wizard->steps);
	}

	public function testProcessStepOneGet() {
		$session = $this->Wizard->Session->read('Wizard');
		$this->assertEmpty($session);

		$this->Wizard->startup($this->Controller);
		$result = $this->Wizard->process('step1');
		$this->assertTrue($result);

		$expectedSession = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'step1',
				'activeStep' => 'step1',
			),
		);
		$resultSession = $this->Wizard->Session->read('Wizard');
		$this->assertEquals($expectedSession, $resultSession);
	}

	public function testProcessStepOnePost() {
		$session = $this->Wizard->Session->read('Wizard');
		$this->assertEmpty($session);
		$this->Wizard->startup($this->Controller);
		// Emulate GET request to set session variables.
		$this->Wizard->process('step1');
		// Emulate POST request.
		$postData = array(
			'User' => array(
				'username' => 'admin',
				'password' => 'pass',
			),
		);
		$this->Wizard->controller->request->data = $postData;
		$CakeResponse = $this->Wizard->process('step1');

		$this->assertInstanceOf('CakeResponse', $CakeResponse);
		$headers = $CakeResponse->header();
		$this->assertContains('/wizard/step2', $headers['Location']);

		$expectedSession = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'step2',
				'activeStep' => 'step1',
			),
			'WizardTest' => array(
				'step1' => $postData,
			),
		);
		$resultSession = $this->Wizard->Session->read('Wizard');
		$this->assertEquals($expectedSession, $resultSession);
	}

	public function testProcessAutovalidatePost() {
		// Set session prerequisites.
		$session = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'gender',
				'activeStep' => 'gender',
			),
			'WizardTest' => array(
				'step1' => array(),
				'step2' => array(),
			),
		);
		$this->Wizard->Session->write('Wizard', $session);

		$this->Wizard->startup($this->Controller);
		$postData = array(
			'WizardUserMock' => array(
				'gender' => 'male',
			),
		);
		$this->Wizard->controller->request->data = $postData;
		$CakeResponse = $this->Wizard->process('gender');

		$this->assertInstanceOf('CakeResponse', $CakeResponse);
		$headers = $CakeResponse->header();
		$this->assertContains('/wizard/step3', $headers['Location']);

		$expectedSession = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'step3',
				'activeStep' => 'gender',
			),
			'WizardTest' => array(
				'step1' => array(),
				'step2' => array(),
				'gender' => $postData,
			),
		);
		$resultSession = $this->Wizard->Session->read('Wizard');
		$this->assertEquals($expectedSession, $resultSession);
	}

	public function testProcessLastStepPost() {
		// Set session prerequisites.
		$session = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'confirmation',
				'activeStep' => 'confirmation',
			),
			'WizardTest' => array(
				'step1' => array(),
				'step2' => array(),
				'gender' => array(),
				'step3' => array(),
				'step4' => array(),
			),
		);
		$this->Wizard->Session->write('Wizard', $session);

		$this->Wizard->startup($this->Controller);
		$postData = array(
			'WizardUserMock' => array(
				'confirm' => '1',
			),
		);
		$this->Wizard->controller->request->data = $postData;
		$CakeResponse = $this->Wizard->process('confirmation');

		$this->assertInstanceOf('CakeResponse', $CakeResponse);
		$headers = $CakeResponse->header();
		$this->assertContains('/wizard', $headers['Location']);

		$expectedSession = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'confirmation',
				'activeStep' => 'confirmation',
			),
			'complete' => array(
				'step1' => array(),
				'step2' => array(),
				'gender' => array(),
				'step3' => array(),
				'step4' => array(),
				'confirmation' => $postData,
			),
		);
		$resultSession = $this->Wizard->Session->read('Wizard');
		$this->assertEquals($expectedSession, $resultSession);
	}

	public function testProcessAfterComplete() {
		// Set session prerequisites.
		$session = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'confirmation',
				'activeStep' => 'confirmation',
			),
			'WizardTest' => array(
				'step1' => array(),
				'step2' => array(),
				'gender' => array(),
				'step3' => array(),
				'step4' => array(),
				'confirmation' => array(),
			),
		);
		$this->Wizard->Session->write('Wizard', $session);

		$this->Wizard->startup($this->Controller);
		$postData = array(
			'WizardUserMock' => array(
				'confirm' => '1',
			),
		);
		$CakeResponse = $this->Wizard->process();

		$this->assertInstanceOf('CakeResponse', $CakeResponse);
		$headers = $CakeResponse->header();
		$this->assertContains('/wizard/step1', $headers['Location']);

		$expectedSession = array(
			'config' => array(
				'steps' => array(
					'step1',
					'step2',
					'gender',
					'step3',
					'step4',
					'confirmation',
				),
				'action' => 'wizard',
				'expectedStep' => 'confirmation',
				'activeStep' => 'confirmation',
			),
			'complete' => array(
				'step1' => array(),
				'step2' => array(),
				'gender' => array(),
				'step3' => array(),
				'step4' => array(),
				'confirmation' => array(),
			),
		);
		$resultSession = $this->Wizard->Session->read('Wizard');
		$this->assertEquals($expectedSession, $resultSession);
	}
}
