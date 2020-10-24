# SPDX-License-Identifier: GPL-2.0

var spdxRegex = "/(SPDX-License-Identifier: .*)/"
var result = file.match(spdxRegex)
if (result == null) {
	return "Missing or malformed SPDX-License-Identifier tag"
} else {
	return result[1]
}
