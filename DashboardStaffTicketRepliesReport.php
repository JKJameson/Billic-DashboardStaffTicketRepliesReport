<?php
class DashboardStaffTicketRepliesReport {
	public $settings = array(
		'name' => 'Dashboard Staff Ticket Replies Report',
		'description' => 'Shows a graph of the last 28 days ticket replies per admin.',
	);
	function dashboard_submodule() {
		global $billic, $db;
		/*
			Staff Ticket Replies
		*/
		$today_end = mktime(23, 59, 59, date('n'), date('j'), date('Y'));
		$max = 28;
		$indexes = array();
		#$x = 1;
		//for($i=$max;$i>0;$i--) {
			//$date = ($today_end-(86400*$i)+86400);
			#$x++;
			//$indexes[date('j M', $date)] = ($max-$i+1);
		//}
		$user_rows = $db->q('SELECT `id`, `firstname`, `lastname` FROM `users` WHERE `permissions` != \'\' ORDER BY `firstname`, `lastname`');
		$ignore = array();
		$replies = array();
		foreach($user_rows as $user_row) {
			$r = $db->q('SELECT COUNT(*) as `count`, DAY(FROM_UNIXTIME(`date`)) as `day`, MONTH(FROM_UNIXTIME(`date`)) as `month` FROM `ticketmessages` WHERE `date` > ? AND `userid` = ? GROUP BY `day`', ($today_end-(86400*($max-1))), $user_row['id']);
			if (empty($r)) {
				$ignore[] = $user_row['id'];
			} else {
				$replies[$user_row['id']] = $r;
			}
		}
		//var_dump($replies);
		
		$html = '<div id="graph-StaffTicketReplies" chartID="StaffTicketReplies" style="width: 100%; height:150px"></div><script>
addLoadEvent(function() {
g = new Dygraph(
      document.getElementById("graph-StaffTicketReplies"),
	  "Date';
		foreach ($user_rows as $u) {
			if (in_array($u['id'], $ignore)) {
				continue;	
			}
			$html .= ','.$u['firstname'].' '.$u['lastname'].'';
		}
		$html .= '\n"+';
		
		$out = '';
		for($i=$max;$i>0;$i--) {
			$date = ($today_end-(86400*$i)+86400);
			$month = date('n', $date);
			$day = date('j', $date);
			$line = '';
			foreach($user_rows as $u) {
				if (in_array($u['id'], $ignore)) {
					continue;	
				}
				$count = 0;
				foreach($replies[$u['id']] as $r) {
					if ($r['month']==$month && $r['day']==$day) {
						$count = $r['count'];
						break;
					}
				}
				$line .= ','.$count;
			}
			// 2009/07/12 12:34
			$out = '"'.date('Y/m/d', $date).$line.'\n"+'.$out;
		}
		$html .= substr($out, 0, -1);
		$html .= ',
		{
			 axes: {
				y: {
					//drawAxis: false,
					//drawGrid: false,
					valueFormatter: function(x) {
						return x.toFixed(0);
					},
				},
				x: {
					//drawAxis: false,
					//drawGrid: false,
				}
			},
			interactionModel: {},
		}
    );
});
</script>';
		return array(
            'header' => 'Staff Ticket Replies (Past 28 Days)',
            'html' => $html,
        );
	}
	
	function exportdata_submodule() {
        global $billic, $db;
        if (empty($_POST['months'])) {
            echo '<form method="POST">';
            echo '<table class="table table-striped" style="width: 400px;"><tr><th colspan="2">Generate Report</th></tr>';
            echo '<tr><td>Months to report</td><td><input type="text" class="form-control" name="months" value="12"></td></tr>';
            echo '<tr><td colspan="2" align="right"><input type="submit" class="btn btn-default" name="generate" value="Generate &raquo"></td></tr>';
            echo '</table>';
            echo '</form>';
            return;
        }
		
		if (!ctype_digit($_POST['months'])) {
			err('Months must be a number');
		}
		if ($_POST['months']>120) {
			err('Months must be 120 or less');
		}
		
        $billic->disable_content();
		
		$today_end = mktime(23, 59, 59, date('n'), date('j'), date('Y'));
		$user_rows = $db->q('SELECT `id`, `firstname`, `lastname` FROM `users` WHERE `permissions` != \'\' ORDER BY `firstname`, `lastname`');
		$replies = array();
		$names = array();
		for($i=1;$i<=$_POST['months'];$i++) {		
			$month_start = ($today_end-(2592000*$i));
			$month_end =   ($today_end-(2592000*($i-1)));
			$month_text = date('M y', $month_start);
			foreach($user_rows as $user_row) {
				$r = $db->q('SELECT COUNT(*) FROM `ticketmessages` WHERE `date` > ? AND `date` < ? AND `userid` = ?', $month_start, $month_end, $user_row['id']);
				$replies[$user_row['id']][$month_text] = $r[0]['COUNT(*)'];
				if (!array_key_exists($user_row['id'], $names)) {
					$names[$user_row['id']] = $user_row['firstname'].' '.$user_row['lastname'];
				}
			}
		}
		
        $row = 'User,';
        foreach($replies as $uid => $months) {
			foreach($months as $month => $count) {
            	$row .= $month.',';
			}
			break;
        }
        echo substr($row, 0, -1)."\r\n";

        foreach($replies as $uid => $months) {
			$row = null;
			foreach($months as $month_text => $count) {
				if ($row===null) {
					$row = $names[$uid].',';	
				}
				$row .= $count.',';
			}
			echo substr($row, 0, -1)."\r\n";
        }

        $output = ob_get_contents();
        ob_end_clean();

        header('Content-Disposition: attachment; filename=exported-'.strtolower($_GET['Module']).'-'.time().'.csv');
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Length: '.strlen($output));
        echo $output;
        exit;

    }
	
}
