<?php

defined('JPATH_BASE') or die;

use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

$d = $displayData;
switch ($d->labelPosition) {
	case '1':
		$direction = 'column';
		break;
	case '0':
	default:
		$direction = 'row';
		break;
}
?>
<div style="display: flex; flex-direction: <?php echo $direction?>">
	<?php echo $d->label ?>
	<?php echo $d->element ?>
</div>
