<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Main extends CI_Controller {

  function __construct()
  {
    parent::__construct();

    /* Standard Libraries of codeigniter are required */
    $this->load->database();
    $this->load->helper('url');
    /* ------------------ */

    $this->load->library('grocery_CRUD');

  }

  public function index()
  {
    echo "<h1>Welcome to the world of Codeigniter</h1>";//Just an example to ensure that we get into the function
    die();
  }

  function _tn_output($output = null)
  {
    $this->load->view('teilnehmer_template.php',$output);
  }

  public function teilnehmer()
  {
    $crud = new grocery_CRUD();
    $crud->set_table('teilnehmer');
    $crud->columns('vorname','nachname','verein','geburtsjahr','geschlecht','email','schnellsteraltenberger','laufserie');
    $crud->unset_texteditor('verein');
    $output = $crud->render();

    $this->_tn_output($output);
  }

  public function korrekturen()
  {
    $crud = new grocery_CRUD();
    $crud->set_table('korrekturen');

    $crud->columns('vorname','nachname','jahrgang','geschlecht','verein','bemerkungen','vornamekorrigiert','nachnamekorrigiert','jahrgangkorrigiert','geschlechtkorrigiert','vereinkorrigiert', 'autor','eintragszeit');
    $crud->fields('vorname','nachname','jahrgang','geschlecht','verein','bemerkungen','autor','vornamekorrigiert','nachnamekorrigiert','jahrgangkorrigiert','geschlechtkorrigiert','vereinkorrigiert');

    $crud->change_field_type('autor','invisible');

    $crud->callback_before_insert(array($this,'fill_author_callback'));

    $output = $crud->render();

    $this->_tn_output($output);
  }

  function fill_author_callback($post_array) {
    $post_array['autor'] = $_SERVER['PHP_AUTH_USER'];
#    $this->session->userdata('user_id');
    return $post_array;
  }        

  public function orgamail()
  {
    $crud = new grocery_CRUD();
    $crud->set_table('orgamail');
    $crud->columns('email');
    $output = $crud->render();
    $this->_tn_output($output);
  }

}

/* End of file Main.php */
/* Location: ./application/controllers/Main.php */
