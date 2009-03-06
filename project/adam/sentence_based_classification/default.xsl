<?xml version="1.0" encoding="ISO-8859-1"?>
<html xsl:version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<script language="javascript" type="text/javascript">
			<![CDATA[

			function toggle(id) {
				if (document.getElementById('H'+id).style.visibility == 'hidden') {
					document.getElementById('H'+id).style.visibility = 'visible';
			    		document.getElementById('H'+id).innerHTML = document.getElementById(id).innerHTML;
				} else {
					document.getElementById('H'+id).style.visibility = 'hidden';
			     		document.getElementById('H'+id).innerHTML = '';
				}
			}

			function openall(n) {
			var N = parseInt(n);
			for (var i = 0; i<N; i++) {
					document.getElementById('HL_'+i.toString()).style.visibility = 'visible';
					document.getElementById('HL_'+i.toString()).innerHTML = document.getElementById('L_'+i.toString()).innerHTML;
				}
			}

			function closeall(n) {
			var N = parseInt(n);
			for (i = 0; i<N; i++){
					document.getElementById('HL_'+i).style.visibility = 'hidden';
					document.getElementById('HL_'+i).innerHTML = '';
				}
			}

			function hilight(obj) {
				obj.style.background = '#FFFFAA';
			}

			function unhilight(obj) {
				obj.style.background = '#FFFFFF';
			}

			]]>
		</script> 
		<title>Report for file <xsl:value-of select="analysis/name"/></title>
	</head>
	<body>
		<div style="background-color:black;color:white;padding:4px">
			<span style="font-weight:bold;font-size:16pt">Report for file <xsl:value-of select="analysis/name"/></span>
		</div>
		<div style="color:black;padding:0px">
			<span style="font-weight:100;font-size:6pt">&lt;<xsl:value-of select="analysis/path"/>&gt;</span>
		</div>
		<br/>
		<div style="background-color:#CCCCCC;padding:4px">
			<span style="font-weight:bold">Report Statistics:</span> <xsl:value-of select="count(analysis
				/statistics/license)"/> license(s) found
		</div>
		<div style="padding:4px">
			<xsl:for-each select="analysis/statistics/license">
				<div style="padding:2px">
					<xsl:if test="position() mod 2 = 1">
						<xsl:attribute name="style">
							<xsl:text>background-color:#EEEEEE</xsl:text>
						</xsl:attribute>
					</xsl:if>
					<xsl:if test="position() mod 2 = 0">
						<xsl:attribute name="style">
							<xsl:text>background-color:#DDDDDD</xsl:text>
						</xsl:attribute>
					</xsl:if>
					<span style="font-weight:bold;font-size:9pt"><xsl:value-of select="name"/></span>
					- <xsl:value-of select="rank"/>
				</div>
			</xsl:for-each>
		</div>
		<br/>
		<div style="background-color:#CCCCCC;padding:4px">
			<span style="font-weight:bold">Sentence breakdown</span>
			<span style="width:500px">:</span>
			<span style="font-weight:100;font-size:6pt">[ </span>
			<span style="font-weight:100;font-size:6pt">
				<xsl:attribute name="onclick">
					openall('<xsl:value-of select="count(analysis/breakdown/sentence)"/>')
				</xsl:attribute>
				openall
			</span>
			<span style="font-weight:100;font-size:6pt"> | </span>
			<span style="font-weight:100;font-size:6pt">
				<xsl:attribute name="onclick">
					closeall(<xsl:value-of select="count(analysis/breakdown/sentence)"/>)
				</xsl:attribute>
				closeall
			</span>
			<span style="font-weight:100;font-size:6pt"> ]</span>
		</div>
		<div>
			<xsl:for-each select="analysis/breakdown/sentence">
				<div>
					<xsl:if test="position() mod 2 = 1">
						<xsl:attribute name="style">
							<xsl:text>background-color:#EEEEEE</xsl:text>
						</xsl:attribute>
					</xsl:if>
					<xsl:if test="position() mod 2 = 0">
						<xsl:attribute name="style">
							<xsl:text>background-color:#DDDDDD</xsl:text>
						</xsl:attribute>
					</xsl:if>
					<xsl:attribute name="onclick">
						toggle('L_<xsl:value-of select="position"/>')
					</xsl:attribute>
					<xsl:value-of select="text"/>
					<div style="visibility:hidden;position:fixed;">
						<xsl:attribute name="id">L_<xsl:value-of select="position"/></xsl:attribute>
						<xsl:attribute name="onclick">
							toggle('L_<xsl:value-of select="position"/>')
						</xsl:attribute>
						<xsl:for-each select="matches/license">
							<div style="background-color:#FFEEEE">
								<u><xsl:value-of select="name"/> ( <xsl:value-of select="rank"/> )</u>
							</div>
							<div style="background-color:#EEFFEE">
								<xsl:value-of select="text"/>
							</div>
						</xsl:for-each>
					</div>
					<div style="visibility:hidden;padding:4px;background-color:black;">
						<xsl:attribute name="id">HL_<xsl:value-of select="position"/></xsl:attribute>
					</div>
				</div>
				<hr/>
			</xsl:for-each>
		</div>
	</body>
</html>
