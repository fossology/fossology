<html>
<head>
<title>Fossology Test Results</title>
</head>
<body>
<table border="1">
	<tr>
		<td colspan="5" align="center"><strong>Test Results on {$runDate}: svn version {$svnVer}</strong></td>
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
<!--
<h3>Test Run Notes</h3>
<p>
{$TestNotes}
</p>
-->
</body>
<!--below does not seem to work for some reason
{html_table loop=$results cols="TestSuite,Passes,Failures,Exceptions,ElapsedTime" rows="5" tr_attr='align="center"'}
-->
<!-- tr_attr='align="center"' cols="Test-Suite,Passes,Failures,Exceptions,Elapsed-Time" -->
</html>
