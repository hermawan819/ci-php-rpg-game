<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class Player
 * @author Plamen Markov <plamen@lynxlake.org>
 * @property Player_model $player_model
 * @property Planet_model $planet_model
 */
class Player extends CI_Controller {

	/**
	 * __construct function.
	 * 
	 * @access public
	 */
	public function __construct() {
        parent::__construct();
		$this->load->model('player_model');
		$this->load->model('planet_model');
	}

	public function indexAction() {
        redirect('player/login');
	}
	
	/**
	 * register function.
	 * 
	 * @access public
	 * @return void
	 */
	public function registerAction() {
		// create the data object
		$data = new stdClass();

		// set validation rules
		$this->form_validation->set_rules('username', 'Username', 'trim|required|alpha_numeric|min_length[4]|is_unique[players.username]', array('is_unique' => 'This username already exists. Please choose another one.'));
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|is_unique[players.email]');
		$this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[1]');
		$this->form_validation->set_rules('password_confirm', 'Confirm Password', 'trim|required|min_length[1]|matches[password]');
		
		if ($this->form_validation->run() === false) {
			// validation not ok, send validation errors to the view
			$this->load->view('header', $data);
			$this->load->view('player/register', $data);
			$this->load->view('footer', $data);
		} else {
			// set variables from the form
			$username = $this->input->post('username');
			$email    = $this->input->post('email');
			$password = $this->input->post('password');
			
			if ($this->player_model->create_player($username, $email, $password)) {
				redirect('/login');
			} else {
				// player creation failed, this should never happen
				$data->error = 'There was a problem creating your new account. Please try again.';
				
				// send error to the view
				$this->load->view('header', $data);
				$this->load->view('player/register', $data);
				$this->load->view('footer', $data);
			}
		}
	}

	/**
	 * profile function.
	 *
	 * @access public
	 * @return void
	 */
	public function profileAction() {
        if (!$this->session->userdata('logged_in')) {
            redirect('login');
        }

		$data = new stdClass();

        $player_id = $this->session->userdata('player_id');
		$data->player = $this->player_model->get_player($player_id);

        $this->load->model('planet_model');
        $data->planet_id = $this->session->userdata('planet_id');
        $data->resources = $this->planet_model->get_resources($data->planet_id);

		// set validation rules
		$this->form_validation->set_rules('username', 'Username', 'trim|required|min_length[4]');
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');

		if ($this->form_validation->run() === false) {
			// validation not ok, send validation errors to the view
			$this->load->view('header', $data);
			$this->load->view('player/profile', $data);
			$this->load->view('footer', $data);
		} else {
			// set variables from the form
			$username = $this->input->post('username');
			$email    = $this->input->post('email');
			$password = $this->input->post('password');
			$password_confirm = $this->input->post('password_confirm');
			$full_name = $this->input->post('full_name');

			if (!empty($password) && $password !== $password_confirm) {
                $this->session->set_flashdata('danger', 'Passwords do not match.');
                redirect('/profile');
            }

            if (0 < $this->player_model->is_username_unique($player_id, $username)) {
                $this->session->set_flashdata('danger', 'This username already exists. Please choose another one.');
                redirect('/profile');
            }

            $fields = array(
                'username' => $username,
                'email' => $email,
                'full_name' => $full_name,
                'updated_at' => date('Y-m-d H:i:s')
            );
            if (!empty($password)) {
                $fields['password'] = $password;
            }

            $this->player_model->edit_profile($player_id, $fields);
            $this->session->set_flashdata('success', 'Profile successfully updated.');
            redirect('/profile');
		}
	}
		
	/**
	 * login function.
	 * 
	 * @access public
	 * @return void
	 */
	public function loginAction() {
		
		// create the data object
		$data = new stdClass();
		
		// set validation rules
		$this->form_validation->set_rules('username', 'Username', 'required');
		$this->form_validation->set_rules('password', 'Password', 'required');
		
		if ($this->form_validation->run() == false) {
			
			// validation not ok, send validation errors to the view
			$this->load->view('header', $data);
			$this->load->view('player/login', $data);
			$this->load->view('footer', $data);
			
		} else {
			
			// set variables from the form
			$username = $this->input->post('username');
			$password = $this->input->post('password');
			
			if ($this->player_model->resolve_player_login($username, $password)) {
				
				$player_id = $this->player_model->get_player_id_from_playername($username);
				$player    = $this->player_model->get_player($player_id);
				$planet_id = $this->player_model->get_planet_id($player_id);

                // set session user data
                $this->session->set_userdata(array(
                    'logged_in' => true,
                    'player_id' => (int)$player->id,
                    'username' => (string)$player->username,
                    'is_confirmed' => (bool)$player->is_confirmed,
                    'is_admin' => (bool)$player->is_admin,
                    'planet_id' => (int)$planet_id,
                    'planet_name' => get_planet_name($this->planet_model->get_planet($planet_id))
                ));

				// user login ok
                if ($this->session->userdata('redirect_url')) {
                    redirect($this->session->userdata('redirect_url'));
                } else {
                    redirect('/');
                }

			} else {
				
				// login failed
				$data->error = 'Wrong username or password.';
				
				// send error to the view
				$this->load->view('header', $data);
				$this->load->view('player/login', $data);
				$this->load->view('footer', $data);
			}
		}
	}
	
	/**
	 * logout function.
	 * 
	 * @access public
	 * @return void
	 */
	public function logoutAction() {
		if (isset($_SESSION['logged_in']) && true === $_SESSION['logged_in']) {
			
			// remove session datas
			foreach ($_SESSION as $key => $value) {
				unset($_SESSION[$key]);
			}

            $this->session->set_flashdata('success', 'Successfully logged out.');
			
			// user logout ok
			redirect('/login');
		} else {
			
			// there user was not logged in, we cannot logged him out,
			// redirect him to site root
			redirect('/');
		}
	}
	
}
