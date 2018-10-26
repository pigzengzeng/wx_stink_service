<?php
if ( ! function_exists('json_pretty'))
{
	/** Json数据格式化
	 * @param  Mixed  $data   数据
	 * @param  String $indent 缩进字符，默认4个空格
	 * @return JSON
	 */
	function json_pretty($data, $indent=null){
		
		
		/** 递归，将元素进行urlencode
		 */
		$json_traverse = function (&$data,&$fun){
			if(is_array($data) || is_object($data) ){
				foreach ($data as $key=>&$val){
					$fun($val,$fun);
				}
			}else{
				if($data!==true && $data!==false && $data!==null){
					$data = urlencode($data);
				}
			}
		};
		$json_traverse($data,$json_traverse);
		
		// 对数组中每个元素递归进行urlencode操作，保护中文字符
// 		if(is_array($data))
// 			array_walk_recursive($data, 'json_format_protect');
		
		
			
				
		// json encode
		$data = json_encode($data);
		
		// 将urlencode的内容进行urldecode
		$data = urldecode($data);
		
		// 缩进处理
		$ret = '';
		$pos = 0;
		$length = strlen($data);
		$indent = isset($indent)? $indent : '    ';
		$newline = "\n";
		$prevchar = '';
		$outofquotes = true;
		
		for($i=0; $i<=$length; $i++){
			
			$char = substr($data, $i, 1);
			
			if($char=='"' && $prevchar!='\\'){
				$outofquotes = !$outofquotes;
			}elseif(($char=='}' || $char==']') && $outofquotes){
				$ret .= $newline;
				$pos --;
				for($j=0; $j<$pos; $j++){
					$ret .= $indent;
				}
			}
			
			$ret .= $char;
			
			if(($char==',' || $char=='{' || $char=='[') && $outofquotes){
				$ret .= $newline;
				if($char=='{' || $char=='['){
					$pos ++;
				}
				
				for($j=0; $j<$pos; $j++){
					$ret .= $indent;
				}
			}
			
			$prevchar = $char;
		}
		
		return $ret;
	}
	
	 

}