/**
 *	Copyright 2009, 2010 Litwicki Media LLC
 *	@author:	jake@litwickimedia.com
 *	@SVN:		$Id$
 */

function add_user(data){
	$('#newuser-modal').dialog('close');
	$("#userlist-body").append('<tr>'
		+'<td>&nbsp;</td>'
		+'<td class="icon"><a class="noborder" href="/profile.php?id=' + data.user_id + '"><span class="user-go ico">&nbsp;</span></a></td>'
		+'<td class="date">'+data.user_regdate+'</td>'
		+'<td class="username">'+data.user_realname+'</td>'
		+'<td class="phone">'+data.user_phone+'</td>'
		+'<td class="desc">...</td>'
	+'</tr>');

	return true;

}