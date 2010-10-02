<html>
<head>
<title>Fossology License Test Results</title>
</head>
<body>
<table border="1" cellpadding="0">
  <tr>
    <td colspan="3" align="center"><strong>Summary of Fossology License Test Results</strong></td>
  </tr>
  <tr>
    <th align="center" colspan="1">Agent</th>
    <th align="center" colspan="1">Pass</th>
    <th align="center" colspan="1">Fail</th>
  </tr>
  {section name=tr loop=$agent}
  <tr>
      <td align="left">{$agent[tr]}</td>
      <td align="center" style="color:#009900">{$pass[tr]}</td>
      <td align="center" style="color:red">{$fail[tr]}</td>
  </tr>
  {/section}
</table>

</body>
</html>

    