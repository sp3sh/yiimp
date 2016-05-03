<?php

require_once("util.php");

class ExplorerController extends CommonController
{
	public $defaultAction='index';
	public $admin = false;

	/////////////////////////////////////////////////

	public function run($actionID)
	{
		$this->admin = user()->getState('yaamp_admin');

		// Forward the url /explorer/BTC to the BTC block explorer
		if (!empty($actionID)) {
			if (is_numeric($actionID) && isset($_REQUEST['id'])) {
				$this->forward('id');
			}
			elseif (strlen($actionID) <= 6 && !isset($_REQUEST['id'])) {
				$coin = getdbosql('db_coins', "symbol=:symbol", array(
					':symbol'=>strtoupper($actionID)
				));
				if ($coin && ($coin->visible || $this->admin)) {
					if (!empty($_POST)) {
						$_GET['SYM'] = $coin->symbol;
						$this->forward('search');
					}
					$_REQUEST['id'] = $coin->id;
					$this->forward('id');
				}
			}
		}
		return parent::run($actionID);
	}

	/////////////////////////////////////////////////

	// Hide coin id from explorer links... created by createUrl()
	public function createUrl($route,$params=array(),$ampersand='&')
	{
		if ($route == '/explorer' && isset($params['id'])) {
			$coin = getdbo('db_coins', intval($params['id']));
			if ($coin && $coin->visible) {
				unset($params['id']);
				$route = '/explorer/'.$coin->symbol.'?'.http_build_query($params,'',$ampersand);
				$params = array();
			}
		}
		return parent::createUrl($route, $params, $ampersand);
	}

	/////////////////////////////////////////////////

	public function actionIndex()
	{
		if(isset($_COOKIE['mainbtc'])) return;
		if(!LimitRequest('explorer')) return;

		$id = getiparam('id');
		$coin = getdbo('db_coins', $id);

		$height = getiparam('height');
		if($coin && intval($height)>0)
		{
			$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
			$hash = $remote->getblockhash(intval($height));
		}

		else
			$hash = getparam('hash');

		$txid = getparam('txid');
		if($coin && !empty($txid) && ctype_xdigit($txid))
		{
			$remote = new Bitcoin($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
			$tx = $remote->getrawtransaction($txid, 1);
			if (!$tx) $tx = $remote->gettransaction($txid);

			$hash = arraySafeVal($tx,'blockhash');
		}

		if($coin && !empty($hash) && ctype_xdigit($hash))
			$this->render('block', array('coin'=>$coin, 'hash'=>substr($hash, 0, 64)));

		else if($coin)
			$this->render('coin', array('coin'=>$coin));

		else
			$this->render('index');
	}

	// alias...
	public function actionId()
	{
		return $this->actionIndex();
	}

	// redirect POST request with url cleanup...
	public function actionSearch()
	{
		$height = getiparam('height');
		$txid = arraySafeVal($_REQUEST,'txid');
		$hash = arraySafeVal($_REQUEST,'hash');
		if (isset($_GET['SYM'])) {
			// only for visible coins
			$url = "/explorer/".$_GET['SYM']."?";
		} else if (isset($_GET['id'])) {
			// only for hidden coins
			$url = "/explorer/".$_GET['id']."?";
		}
		if (!empty($height)) $url .= "&height=$height";
		if (!empty($txid)) $url .= "&txid=$txid";
		if (!empty($hash)) $url .= "&hash=$hash";

		return $this->redirect(str_replace('?&', '?', $url));
	}

	/**
	 * Difficulty Graph
	 */
	public function actionGraph()
	{
		$id = getiparam('id');
		$coin = getdbo('db_coins', $id);
		if ($coin)
			$this->renderPartial('graph', array('coin'=>$coin));
		else
			echo "[]";
	}
}
