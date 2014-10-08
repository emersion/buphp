<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require('vendor/autoload.php');
require('lib/mime.php');

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

$opts = array(
	'bupCmd' => '/usr/bin/bup',
	'bupDir' => '/var/backups/.bup',
	//'bupDir' => '/home/simon/.bup',
	'webServerPort' => 8080
);

function getWebServerPid() {
	return trim(file_get_contents('var/bup-web.pid'));
}
function isWebServerStarted() {
	$pid = getWebServerPid();

	if (empty($pid)) {
		return false;
	}

	$process = new Process('ps --no-headers -p '.(int) $pid);
	$process->run();
	
	$output = $process->getOutput();
	if (empty($output)) {
		return false;
	}

	return true;
}

$cmd = (!empty($_GET['cmd'])) ? $_GET['cmd'] : '';
$action = (!empty($_GET['a'])) ? $_GET['a'] : '';

$serverUrl = 'http://'.$_SERVER['HTTP_HOST'].':'.$opts['webServerPort'];

$view = array(
	'webServerStarted' => isWebServerStarted(),
	'webServerUrl' => $serverUrl,

	'fuseStarted' => false
);

switch ($cmd) {
	case 'web':
		if (empty($action) or $action == 'start') {
			$started = isWebServerStarted();

			if (!$started) {
				$builder = new ProcessBuilder();
				$builder->setPrefix(array('nohup', $opts['bupCmd'], '-d', $opts['bupDir']));
				$builder->setArguments(array('web', '0.0.0.0:'.$opts['webServerPort']));
				$cmd = $builder->getProcess()->getCommandLine();

				$process = new Process($cmd.' > var/bup-web.log 2>&1 & echo $!');

				$process->run();

				file_put_contents('var/bup-web.pid', $process->getOutput());
				@chmod('var/bup-web.pid', 0777);

				$view['output'] = file_get_contents('var/bup-web.log');

				$started = $process->isSuccessful();
			}

			if (!$started) {
				$view['error'] = 'Cannot start web server';
			} else {
				$view['success'] = 'Server started at <a href="'.$serverUrl.'" target="_blank">'.$serverUrl.'</a>';
				$view['webServerStarted'] = true;
			}
		} elseif ($action == 'stop') {
			if (!isWebServerStarted()) {
				$view['error'] = 'Server not started yet';
				break;
			}

			$pid = getWebServerPid();

			$builder = new ProcessBuilder();
			$builder->setArguments(array('kill', $pid));

			$process = $builder->getProcess();
			$process->run();

			if (!$process->isSuccessful()) {
				$view['error'] = 'Cannot stop web server';
				$view['output'] = $process->getErrorOutput();
			} else {
				file_put_contents('var/bup-web.pid', '');
				$view['output'] = $process->getOutput();
				$view['webServerStarted'] = false;
				$view['success'] = 'Server stopped';
			}
		}
		break;
	case 'ls':
		$builder = new ProcessBuilder();
		$builder->setPrefix(array($opts['bupCmd'], '-d', $opts['bupDir']));
		$builder->setArguments(array('ls', $action));

		$process = $builder->getProcess();
		$process->run();

		if ($process->isSuccessful()) {
			$output = trim($process->getOutput());

			if ($output == $action) { //Display a file
				$builder->setArguments(array('cat-file', $action));

				$process = $builder->getProcess();
				$process->run();

				if ($process->isSuccessful()) {
					$mime = system_extension_mime_type($action);
					if (empty($mime)) {
						$mime = 'application/octet-stream';
					}

					header('Content-Type: '.$mime);

					echo $process->getOutput();
					exit();
				} else {
					$view['error'] = 'Cannot list <em>'.$action.'</em>';
					$view['output'] = $process->getErrorOutput();
				}
				break;
			}

			$itemsNames = preg_split('#\s+#', $output);
			$items = array();

			foreach ($itemsNames as $i => $name) {
				$name = trim($name);
				if (empty($name)) {
					continue;
				}

				if (substr($name, -1) == '@') {
					$name = substr($name, 0, -1);
				}

				$path = (empty($action)) ? $name : str_replace('//', '/', $action.'/'.$name);

				$items[$path] = $name;
			}

			$view['list'] = $items;
		} else {
			$view['error'] = 'Cannot list <em>'.$action.'</em>';
			$view['output'] = $process->getErrorOutput();
		}
		break;
	case 'fuse':
		/*$mountPoint = realpath('var').'/mount';

		if (!is_dir($mountPoint)) {
			mkdir($mountPoint);
			chmod($mountPoint, 0777);
		}

		if (empty($action) or $action == 'start') {
			$builder = new ProcessBuilder();
			$builder->setPrefix(array($opts['bupCmd'], '-d', $opts['bupDir']));
			$builder->setArguments(array('fuse', '-o', $mountPoint));
			$cmd = $builder->getProcess()->getCommandLine();
var_dump($cmd);
			$process = new Process($cmd.' > var/bup-fuse.log 2>&1 & echo $!');

			$process->run();

			$serverUrl = 'ssh://backup@'.$_SERVER['HTTP_HOST'].':'.$mountPoint;

			if ($process->isSuccessful()) {
				$view['success'] = 'You can access backups on <a href="'.$serverUrl.'" target="_blank">'.$serverUrl.'</a>';

				file_put_contents('var/bup-fuse.pid', trim($process->getOutput()));
				chmod('var/bup-fuse.pid', 0777);
			} else {
				$view['error'] = 'Cannot mount backups';
				$view['output'] = $process->getErrorOutput();
			}

			$view['fuseStarted'] = true;
		} elseif ($action == 'stop') {
			$pid = file_get_contents('var/bup-fuse.pid');

			$builder = new ProcessBuilder();
			$builder->setArguments(array('kill', $pid));

			$process = $builder->getProcess();
			$process->run();

			if (!$process->isSuccessful()) {
				$view['error'] = 'Cannot stop fuse server';
				$view['output'] = $process->getErrorOutput();
			} else {
				file_put_contents('var/bup-fuse.pid', '');
				$view['output'] = $process->getOutput();
				$view['fuseStarted'] = false;
				$view['success'] = 'Server stopped';
			}
		}
		break;*/
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Buphp</title>

	<link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
</head>
<body>
	<div class="container">
		<h1>Buphp</h1>

		<p>
			<a href="?cmd=ls" class="btn btn-primary btn-lg">List backups</a>
			<?php
			/*if (!$view['fuseStarted']) {
				?>
				<a href="?cmd=fuse" class="btn btn-primary btn-lg">Mount backups</a>
				<?php
			} else {
				?>
				<a href="?cmd=fuse&amp;a=stop" class="btn btn-danger btn-lg">Unmount backups</a>
				<?php
			}*/

			if (!$view['webServerStarted']) {
				?>
				<a href="?cmd=web" class="btn btn-primary btn-lg">Start web server</a>
				<?php
			} else {
				?>
				<a href="<?php echo $view['webServerUrl']; ?>" class="btn btn-primary btn-lg" target="_blank">Browse backups</a>
				<a href="?cmd=web&amp;a=stop" class="btn btn-danger btn-lg">Stop web server</a>
				<?php
			}
			?>
		</p>

		<?php
		if (!empty($view['error'])) {
			?>
			<div class="alert alert-danger">
				<strong>Oh snap!</strong> <?php echo $view['error']; ?>
			</div>
			<?php
		}
		if (!empty($view['success'])) {
			?>
			<div class="alert alert-success">
				<strong>Well done!</strong> <?php echo $view['success']; ?>
			</div>
			<?php
		}

		if (!empty($view['list'])) {
			?>
			<h2>Backups list</h2>

			<ol class="breadcrumb">
				<li><a href="?cmd=ls">Root</a></li>
				<?php
				$path = '';
				foreach (explode('/', $action) as $part) {
					if (empty($part)) {
						continue;
					}

					$path .= $part.'/';
					?>
					<li><a href="?cmd=ls&amp;a=<?php echo $path; ?>"><?php echo $part; ?></a></li>
					<?php
				}
				?>
			</ol>

			<div class="list-group">
				<?php
				foreach ($view['list'] as $path => $name) {
					?>
					<a href="?cmd=ls&amp;a=<?php echo $path; ?>" class="list-group-item"><?php echo $name; ?></a>
					<?php
				}
				?>
			</div>
			<?php
		}

		if (!empty($view['output'])) {
			?>
			<pre style="background-color: black; color: white;"><?php echo $view['output']; ?></pre>
			<?php
		}
		?>
	</div>

	<script type="text/javascript" src="assets/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="assets/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>