<html>
<head>
<title>Fossology License Test Results</title>
</head>
<body>
<table border="1">
	<tr>
		<td colspan="13" align="center"><strong>Test Results on {$runDate}: svn version {$svnVer}</strong></td>
	</tr>
  <tr>
    <th align="center" colspan="1">File/Vetted Name</th>
    <th align="center" colspan="4">Nomos</th>
    <th align="center" colspan="4">BSam</th>
    <th align="center" colspan="4">FoNomos</th>
  </tr>
	<tr>
		<th align="center">Result</th>
		<th align="center">Pass</th>
		<th align="center">Fail</th>
		<th align="center">Missed</th>
	</tr>
	<!--
	<tr>
	  <td align="left">{$file_name}</td>
	  <td align="center">{$pass}</td>
	  <td align="center">{$fail}</td>
	  <td align="center">{$missed}</td>
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
</body>
<!--below does not seem to work for some reason
{html_table loop=$results cols="TestSuite,Passes,Failures,Exceptions,ElapsedTime" rows="5" tr_attr='align="center"'}
-->
<!-- tr_attr='align="center"' cols="Test-Suite,Passes,Failures,Exceptions,Elapsed-Time" -->
</html>
