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

namespace OCA\Activity\Tests\Unit;

use OCA\Activity\Navigation;
use OCA\Activity\Tests\Unit\Mock\Extension;

/**
 * Class NavigationTest
 *
 * @package OCA\Activity\Tests
 * @group DB
 */
class NavigationTest extends TestCase {
	public function getTemplateData() {
		return [
			['all'],
			['all', 'self'],
			['all', 'self', 'thisIsTheRSSToken'],
			['random'],
		];
	}

	/**
	 * @dataProvider getTemplateData
	 */
	public function testGetTemplate($constructorActive, $forceActive = null, $rssToken = '') {
		$activityLanguage = \OCP\Util::getL10N('activity', 'en');
		$activityManager = new \OC\Activity\Manager(
			$this->createMock('OCP\IRequest'),
			$this->createMock('OCP\IUserSession'),
			$this->createMock('OCP\IConfig')
		);
		$activityManager->registerExtension(function () use ($activityLanguage) {
			return new Extension($activityLanguage, $this->createMock('\OCP\IURLGenerator'));
		});
		$navigation = new Navigation(
			$activityLanguage,
			$activityManager,
			\OC::$server->getURLGenerator(),
			'test',
			$rssToken,
			$constructorActive
		);
		$output = $navigation->getTemplate($forceActive)->fetchPage();

		// Get only the template part with the navigation links
		$navigationLinks = \substr($output, \strpos($output, '<ul>') + 4);
		$navigationLinks = \substr($navigationLinks, 0, \strrpos($navigationLinks, '</li>'));

		// Remove tabs and new lines
		$navigationLinks = \str_replace(["\t", "\n"], '', $navigationLinks);

		// Turn the list of links into an array
		$navigationEntries = \explode('</li>', $navigationLinks);

		$links = $navigation->getLinkList();

		// Check whether all top links are available
		foreach ($links['top'] as $link) {
			$found = false;
			foreach ($navigationEntries as $navigationEntry) {
				if (\strpos($navigationEntry, 'data-navigation="' . $link['id'] . '"') !== false) {
					$found = true;
					$this->assertContains(
						'href="' . $link['url'] . '">' . $link['name']. '</a>',
						$navigationEntry
					);
					if ($forceActive == $link['id'] || ($forceActive == null && $constructorActive == $link['id'])) {
						$this->assertStringStartsWith('<li class="active">', $navigationEntry);
					} else {
						$this->assertStringStartsWith('<li>', $navigationEntry);
					}
				}
			}
			$this->assertTrue($found, 'Could not find navigation entry "' . $link['name'] . '"');
		}

		// Check size of app links
		$this->assertSame(1, \sizeof($links['apps']));
		$this->assertNotContains('data-navigation="files"', $navigationLinks, 'Files app should not be included when there are no other apps.');

		if ($rssToken) {
			$rssInputField = \strpos($output, 'input id="rssurl"');
			$this->assertGreaterThan(0, $rssInputField);
			$endOfInputField = \strpos($output, ' />', $rssInputField);

			$this->assertNotSame(false, \strpos(\substr($output, $rssInputField, $endOfInputField - $rssInputField), $rssToken));
		}
	}
}
