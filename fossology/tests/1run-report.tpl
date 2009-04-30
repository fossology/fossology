y
<html>
<head>
<title>Fossology Test Results</title>
</head>
<body>
<h2>Fossology Test Results</h2>
<table border="1">
	<tr>
		<td colspan="5" align="center">Test Results on {$runDate}: svn version {$svnVer}</td>
	</tr>
	<tr>
		<th align="center">Test Suite</th>
		<th align="center">Passes</th>
		<th align="center">Failures</th>
		<th align="center">Exceptions</th>
		<th align="center">Elapsed Time</th>
	</tr>
	<!--
	<tr><td align="left">{$test_name}</td><td align="center">{$passes}</td>
	<td align="center">{$failures}</td><td align="center">{$exceptions}</td><td align="center">{$etime}</td>
	</tr>
	-->
	{section name=tr loop=$results step=$cols}
  <tr>
    {section name=td start=$smarty.section.tr.index loop=$smarty.section.tr.index+$cols}
    <td align="center">{$results[td]|default:"&nbsp;"}</td>
    {/section}
  </tr>
  {/section}
</table>
<h3>Test Run Notes</h3>
<p>
{$TestNotes}
</p>
</body>
</html>
