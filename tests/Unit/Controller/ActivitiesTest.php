<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Activity\Tests\Controller;

use OCA\Activity\Controller\Activities;
use OCA\Activity\Tests\Unit\TestCase;
use OCP\Template;

/**
 * Class ActivitiesTest
 *
 * @group DB
 * @package OCA\Activity\Tests\Controller
 */
class ActivitiesTest extends TestCase {
	/** @var \OCP\IRequest|\PHPUnit_Framework_MockObject_MockObject */
	protected $request;

	/** @var \OCP\IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var \OCA\Activity\Data|\PHPUnit_Framework_MockObject_MockObject */
	protected $data;

	/** @var \OCA\Activity\Navigation|\PHPUnit_Framework_MockObject_MockObject */
	protected $navigation;

	/** @var \OCP\IAvatarManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $avatarManager;

	/** @var Activities */
	protected $controller;

	protected function setUp() {
		parent::setUp();

		$this->config = $this->getMockBuilder('OCP\IConfig')
			->disableOriginalConstructor()
			->getMock();
		$this->data = $this->getMockBuilder('OCA\Activity\Data')
			->disableOriginalConstructor()
			->getMock();
		$this->navigation = $this->getMockBuilder('OCA\Activity\Navigation')
			->disableOriginalConstructor()
			->getMock();

		$this->request = $this->createMock('OCP\IRequest');

		$this->controller = $this->getController();

		$this->avatarManager = $this->getMockBuilder('OCP\IAvatarManager')
			->disableOriginalConstructor()
			->getMock();
		$this->overwriteService('AvatarManager', $this->avatarManager);
	}

	public function tearDown() {
		$this->restoreService('AvatarManager');
		parent::tearDown();
	}

	protected function getController(array $methods = []) {
		if (empty($methods)) {
			return new Activities(
				'activity',
				$this->request,
				$this->config,
				$this->data,
				$this->navigation
			);
		} else {
			return $this->getMockBuilder('OCA\Activity\Controller\Activities')
				->setConstructorArgs([
					'activity',
					$this->request,
					$this->config,
					$this->data,
					$this->navigation
				])
				->setMethods($methods)
				->getMock();
		}
	}

	public function testShowList() {
		$template = new Template('activity', 'stream.app.navigation', '');
		$template->assign('activeNavigation', 'all');
		$template->assign('navigations', []);
		$template->assign('rssLink', '');
		$this->navigation->expects($this->any())
			->method('getTemplate')
			->willReturn($template);

		$avatar = $this->getMockBuilder('OCP\IAvatar')
			->disableOriginalConstructor()
			->getMock();
		$this->avatarManager->expects($this->once())
			->method('getAvatar')
			->willReturn($avatar);
		$avatar->expects($this->once())
			->method('exists')
			->willReturn(false);

		$templateResponse = $this->controller->showList();
		$this->assertInstanceOf('\OCP\AppFramework\Http\TemplateResponse', $templateResponse, 'Asserting type of return is \OCP\AppFramework\Http\TemplateResponse');

		$renderedResponse = $templateResponse->render();
		$this->assertNotEmpty($renderedResponse);
	}
}
