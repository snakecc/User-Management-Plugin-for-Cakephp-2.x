<?php

/**
 * Class UserAuthComponent
 *
 * @property SessionComponent $Session
 * @property CookieComponent $Cookie
 * @property ControllerListComponent $ControllerList
 */
class UserAuthComponent extends Component {

	/**
	 * This component uses following components
	 *
	 * @var array
	 */
	var $components = array('Session', 'Cookie', 'RequestHandler', 'Usermgmt.ControllerList');

	/**
	 * configure key
	 *
	 * @var string
	 */
	var $configureKey = 'User';

	function initialize(Controller $controller) {

	}

	function __construct(ComponentCollection $collection, $settings = array()) {
		parent::__construct($collection, $settings);
	}

	function startup(Controller $controller = null) {

	}

	/**
	 * Called before the controller action.  You can use this method to configure and customize components
	 * or perform logic that needs to happen before each controller action.
	 *
	 * @param object $c current controller object
	 * @return void
	 */
	function beforeFilter(&$c) {
		UsermgmtInIt();
		$user = $this->__getActiveUser();
		//debug($user);

		$pageRedirect = $c->Session->read('permission_error_redirect');
		$c->Session->delete('permission_error_redirect');

		$controller = $c->params['controller'];
		$action = $c->params['action'];
		$actionUrl = $controller . '/' . $action;
		$requested = (isset($c->params['requested']) && $c->params['requested'] == 1) ? true : false;
		$access = str_replace(' ', '', ucwords(str_replace('_', ' ', $controller))) . '/' . $action;
		$allControllers = $this->ControllerList->getControllerWithMethods();
		$errorPage = false;
		if (!in_array($access, $allControllers)) {
			$errorPage = true;
		}
		$permissionFree = array('users/login', 'users/logout', 'users/register', 'users/userVerification', 'users/forgotPassword', 'users/activatePassword', 'pages/display', 'users/accessDenied', 'users/emailVerification');
		if ((empty($pageRedirect) || $actionUrl != 'users/login') && !$requested && !in_array($actionUrl, $permissionFree) && !$errorPage) {
			App::import('Model', 'Usermgmt.UserGroup');
			$userGroupModel = new UserGroup;
			if (!$this->isLogged()) {
				if (!$userGroupModel->isGuestAccess($controller, $action)) {
					//$c->log('permission: actionUrl-' . $actionUrl, LOG_DEBUG);
					$c->Session->write('permission_error_redirect', '/users/login');
					$c->Session->setFlash('You need to be signed in to view this page.');
					$cUrl = '/' . $c->params->url;
					if (!empty($_SERVER['QUERY_STRING'])) {
						$rUrl = $_SERVER['REQUEST_URI'];
						$pos = strpos($rUrl, $cUrl);
						$cUrl = substr($rUrl, $pos, strlen($rUrl));
					}
					$c->Session->write('Usermgmt.OriginAfterLogin', $cUrl);
					$c->redirect('/login');
				}
			} else {
				if (!$userGroupModel->isUserGroupAccess($controller, $action, $this->getGroupId())) {
					//$c->log('permission: ActionUrl-' . $actionUrl, LOG_DEBUG);
					$c->Session->write('permission_error_redirect', '/users/login');
					$c->redirect('/accessDenied');
				}
			}
		}
		if (!empty($user) && $actionUrl !== 'users/login' && $user['User']['id'] != 1
			&& (!$user['User']['active'] || $user['User']['active'] > date('Y-m-d'))
		) {
			$c->Session->setFlash('Your account has been suspended or expired. Please contact administrator of this website.');
			$c->redirect('/login');
		}
	}

	/**
	 * Used to check whether user is logged in or not
	 *
	 * @access public
	 * @return boolean
	 */
	public function isLogged() {
		return ($this->getUserId() !== null);
	}

	/**
	 * Used to get user from session
	 *
	 * @access public
	 * @return array
	 */
	public function getUser() {
		return $this->Session->read('UserAuth');
	}

	/**
	 * Used to get user id from session
	 *
	 * @access public
	 * @return integer
	 */
	public function getUserId() {
		return $this->Session->read('UserAuth.User.id');
	}

	/**
	 * Used to get group id from session
	 *
	 * @access public
	 * @return integer
	 */
	public function getGroupId() {
		return $this->Session->read('UserAuth.User.user_group_id');
	}

	/**
	 * Used to get group name from session
	 *
	 * @access public
	 * @return string
	 */
	public function getGroupName() {
		return $this->Session->read('UserAuth.UserGroup.name');
	}

	/**
	 * Used to check is admin logged in
	 *
	 * @access public
	 * @return string
	 */
	public function isAdmin() {
		$groupId = $this->Session->read('UserAuth.User.user_group_id');
		if ($groupId == ADMIN_GROUP_ID) {
			return true;
		}
		return false;
	}

	/**
	 * Used to check is guest logged in
	 *
	 * @access public
	 * @return string
	 */
	public function isGuest() {
		$groupId = $this->Session->read('UserAuth.User.user_group_id');
		if (empty($groupId)) {
			return true;
		}
		return false;
	}

	/**
	 * Used to make password in hash format
	 *
	 * @access public
	 * @param string $pass password of user
	 * @param string $salt optional | default is `null`
	 * @return hash
	 */
	public function makePassword($pass, $salt = null) {
		return md5(md5($pass) . md5($salt));
	}

	/**
	 * Used to make salt in hash format
	 *
	 * @access public
	 * @return hash
	 */
	public function makeSalt() {
		$rand = mt_rand(0, 32);
		$salt = md5($rand . time());
		return $salt;
	}

	/**
	 * Used to maintain login session of user
	 *
	 * @access public
	 * @param mixed $type possible values 'guest', 'cookie', user array
	 * @param string $credentials credentials of cookie, default null
	 * @return array
	 */
	public function login($type = 'guest', $credentials = null) {
		$user = array();
		if (is_string($type) && ($type == 'guest' || $type == 'cookie')) {
			App::import('Model', 'Usermgmt.User');
			$userModel = new User;
			$user = $userModel->authsomeLogin($type, $credentials);
		} elseif (is_array($type)) {
			$user = $type;
		}
		Configure::write($this->configureKey, $user);
		$this->Session->write('UserAuth', $user);
		return $user;
	}

	/**
	 * Used to delete user session and cookie
	 *
	 * @access public
	 * @return void
	 */
	public function logout() {
		$this->Session->delete('UserAuth');
		Configure::write($this->configureKey, array());
		$this->Cookie->delete(LOGIN_COOKIE_NAME);
	}

	/**
	 * Used to persist cookie for remember me functionality
	 *
	 * @access public
	 * @param string $duration duration of cookie life time on user's machine
	 * @return void
	 */
	public function persist($duration = '2 weeks') {
		App::import('Model', 'Usermgmt.User');
		$userModel = new User;
		$token = $userModel->authsomePersist($this->getUserId(), $duration);
		$token = $token . ':' . $duration;
		$this->Cookie->write(LOGIN_COOKIE_NAME, $token, true, $duration);
	}

	/**
	 * Used to check user's session if user's session is not available then it tries to get login from cookie if it exist
	 *
	 * @access private
	 * @throws Exception
	 * @return array
	 */
	private function __getActiveUser() {
		$user = Configure::read($this->configureKey);
		if (!empty($user)) {
			return $user;
		}

		$this->__useSession() || $this->__useCookieToken() || $this->__useGuestAccount();

		$user = Configure::read($this->configureKey);
		if (is_null($user)) {
			throw new Exception('Unable to initialize user');
		}
		return $user;
	}

	/**
	 * Used to get user from session
	 *
	 * @access private
	 * @return boolean
	 */
	private function __useSession() {
		$userId = $this->getUserId();
		if (!$userId) {
			return false;
		}
		App::import('Model', 'Usermgmt.User');
		$userModel = new User;
		$user = $userModel->find('first', array('conditions' => array('User.id' => $userId)));

		Configure::write($this->configureKey, $user);
		return true;
	}

	/**
	 * Used to get login from cookie
	 *
	 * @access private
	 * @return boolean
	 */
	private function __useCookieToken() {
		$token = $this->Cookie->read(LOGIN_COOKIE_NAME);
		if (!$token) {
			return false;
		}
		$user = false;
		$duration = '2 weeks';
		// Extract the duration appendix from the token
		if (strpos($token, ':') !== false) {
			$tokenParts = explode(':', $token);
			$duration = array_pop($tokenParts);
			$token = join(':', $tokenParts);
			$user = $this->login('cookie', compact('token', 'duration'));
			// Delete the cookie once its been used
		}
		$this->Cookie->delete(LOGIN_COOKIE_NAME);
		if (!$user) {
			return false;
		}
		$this->persist($duration);
		return (bool)$user;
	}

	/**
	 * Used to get login as guest
	 *
	 * @access private
	 * @return array
	 */
	private function __useGuestAccount() {
		return $this->login('guest');
	}

}
