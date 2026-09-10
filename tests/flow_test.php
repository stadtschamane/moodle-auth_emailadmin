<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Signup/confirm/login flow tests for auth_emailadmin.
 *
 * @package   auth_emailadmin
 * @copyright 2026 onwards stadtschamane
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace auth_emailadmin;

use advanced_testcase;

/**
 * Full flow test: signup via user_signup, admin confirm, login.
 *
 * @package   auth_emailadmin
 * @copyright 2026 onwards stadtschamane
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flow_test extends advanced_testcase {

    /**
     * Test the full signup -> admin confirmation -> login chain with the
     * email-derived username.
     *
     * @covers \auth_plugin_emailadmin::user_signup
     * @covers \auth_plugin_emailadmin::user_confirm
     * @covers \auth_plugin_emailadmin::user_login
     */
    public function test_signup_confirm_login(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/auth/emailadmin/auth.php');
        require_once($CFG->dirroot . '/user/editlib.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->registerauth = 'emailadmin';

        $auth = get_auth_plugin('emailadmin');

        // Mimic the data login/signup.php would hand to user_signup,
        // including signup_setup_new_user() (sets confirmed, secret, auth...).
        $user = new \stdClass();
        $user->email = 'robert.sack@example.com';
        $user->password = 'ChangeMe!2026';
        $user->firstname = 'Robert';
        $user->lastname = 'Sack';
        $user->city = 'Vienna';
        $user->country = 'AT';
        $user->lang = 'en';
        $user->username = ''; // Derived by the plugin from the email address.
        $user = signup_setup_new_user($user);

        $this->assertTrue($auth->user_signup($user, false));

        $newuser = $DB->get_record('user', ['username' => 'robert.sack']);
        $this->assertNotEmpty($newuser, 'User was created with the derived username');
        $this->assertEquals('robert.sack@example.com', $newuser->email);
        $this->assertEquals(0, $newuser->confirmed, 'New user starts unconfirmed');
        $this->assertEquals('emailadmin', $newuser->auth);

        // Unconfirmed user must not log in.
        $this->assertFalse($auth->user_login('robert.sack', 'ChangeMe!2026'));

        // Admin confirms via the mail link logic.
        $this->assertEquals(AUTH_CONFIRM_OK, $auth->user_confirm('robert.sack', $newuser->secret));
        $newuser = $DB->get_record('user', ['username' => 'robert.sack']);
        $this->assertEquals(1, $newuser->confirmed, 'User confirmed by admin');

        // Confirmed user logs in with the derived username.
        $this->assertTrue($auth->user_login('robert.sack', 'ChangeMe!2026'));
        $this->assertFalse($auth->user_login('robert.sack', 'WrongPassword!'));
    }
}