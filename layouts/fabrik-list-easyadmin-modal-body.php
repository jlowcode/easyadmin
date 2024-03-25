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

empty($d->label) ? $margin = '' : $margin = 'margin-top: 15px;';
?>
<div style=" <?php echo $margin ?> ">
	<?php echo $d->label ?>
	<?php echo $d->element ?>
</div>
