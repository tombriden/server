<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Tests\Settings;

use OC\Settings\Admin\Sharing;
use OC\Settings\Manager;
use OC\Settings\Mapper;
use OC\Settings\Section;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use Test\TestCase;

class ManagerTest extends TestCase {
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	private $manager;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var IDBConnection|\PHPUnit_Framework_MockObject_MockObject */
	private $dbConnection;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $encryptionManager;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var ILockingProvider|\PHPUnit_Framework_MockObject_MockObject */
	private $lockingProvider;
	/** @var Mapper|\PHPUnit_Framework_MockObject_MockObject */
	private $mapper;

	public function setUp() {
		parent::setUp();

		$this->logger = $this->getMockBuilder('\OCP\ILogger')->getMock();
		$this->dbConnection = $this->getMockBuilder('\OCP\IDBConnection')->getMock();
		$this->l10n = $this->getMockBuilder('\OCP\IL10N')->getMock();
		$this->config = $this->getMockBuilder('\OCP\IConfig')->getMock();
		$this->encryptionManager = $this->getMockBuilder('\OCP\Encryption\IManager')->getMock();
		$this->userManager = $this->getMockBuilder('\OCP\IUserManager')->getMock();
		$this->lockingProvider = $this->getMockBuilder('\OCP\Lock\ILockingProvider')->getMock();
		$this->mapper = $this->getMockBuilder(Mapper::class)->disableOriginalConstructor()->getMock();

		$this->manager = new Manager(
			$this->logger,
			$this->dbConnection,
			$this->l10n,
			$this->config,
			$this->encryptionManager,
			$this->userManager,
			$this->lockingProvider,
			$this->mapper
		);
	}

	public function testSetupSettingsUpdate() {
		$this->mapper->expects($this->any())
			->method('has')
			->with('admin_settings', 'OCA\Files\Settings\Admin')
			->will($this->returnValue(true));

		$this->mapper->expects($this->once())
			->method('update')
			->with('admin_settings',
				'class',
				'OCA\Files\Settings\Admin', [
					'section' => 'additional',
					'priority' => 5
				]);
		$this->mapper->expects($this->never())
			->method('add');

		$this->manager->setupSettings([
			'admin' => 'OCA\Files\Settings\Admin',
		]);
	}

	public function testSetupSettingsAdd() {
		$this->mapper->expects($this->any())
			->method('has')
			->with('admin_settings', 'OCA\Files\Settings\Admin')
			->will($this->returnValue(false));

		$this->mapper->expects($this->once())
			->method('add')
			->with('admin_settings', [
				'class' => 'OCA\Files\Settings\Admin',
				'section' => 'additional',
				'priority' => 5
			]);

		$this->mapper->expects($this->never())
			->method('update');

		$this->manager->setupSettings([
			'admin' => 'OCA\Files\Settings\Admin',
		]);
	}

	public function testGetAdminSections() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->mapper->expects($this->once())
			->method('getAdminSectionsFromDB')
			->will($this->returnValue([
				['class' => '\OCA\LogReader\Settings\Section', 'priority' => 90]
			]));

		$this->mapper->expects($this->once())
			->method('getAdminSettingsCountFromDB')
			->will($this->returnValue([
				'logging' => 1
			]));

		$this->assertEquals([
			0 => [new Section('server', 'Server settings', 0)],
			5 => [new Section('sharing', 'Sharing', 0)],
			45 => [new Section('encryption', 'Encryption', 0)],
			90 => [new \OCA\LogReader\Settings\Section(\OC::$server->getL10N('logreader'))],
			98 => [new Section('additional', 'Additional settings', 0)],
			99 => [new Section('tips-tricks', 'Tips & tricks', 0)],
		], $this->manager->getAdminSections());
	}

	public function testGetAdminSectionsEmptySection() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->mapper->expects($this->once())
			->method('getAdminSectionsFromDB')
			->will($this->returnValue([
				['class' => '\OCA\LogReader\Settings\Section', 'priority' => 90]
			]));

		$this->mapper->expects($this->once())
			->method('getAdminSettingsCountFromDB')
			->will($this->returnValue([]));

		$this->assertEquals([
			0 => [new Section('server', 'Server settings', 0)],
			5 => [new Section('sharing', 'Sharing', 0)],
			45 => [new Section('encryption', 'Encryption', 0)],
			98 => [new Section('additional', 'Additional settings', 0)],
			99 => [new Section('tips-tricks', 'Tips & tricks', 0)],
		], $this->manager->getAdminSections());
	}

	public function testGetAdminSettings() {
		$this->mapper->expects($this->any())
			->method('getAdminSettingsFromDB')
			->will($this->returnValue([]));

		$this->assertEquals([
			0 => [new Sharing($this->config)],
		], $this->manager->getAdminSettings('sharing'));
	}
}
