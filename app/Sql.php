<?php
/*
 * Copyright (c) 2021 Guillaume Outters
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

class Sql
{
	public function req($req)
	{
		$this->_params = func_get_args();
		array_shift($this->_params);
		$req = preg_replace_callback('/%(?:{([sdt,]+)}|([sdt]))/', array($this, '_pourcentEnVal'), $req);
		return $req;
	}
	
	protected function _pourcentEnVal($pourcent)
	{
		$param = array_shift($this->_params);
		$fchamp = function($pourcent, $val)
		{
			if(!isset($val)) return 'null';
			switch($pourcent)
			{
				case 's': return "'".strtr($val, array("'" => "''"))."'";
				case 't': return preg_replace('#^([0-9]{2})/([0-9]{2})/([0-9]{4})#', '\3-\2-\1', $val);
				case 'd': return $val;
				default: throw new Exception("Conversion %$pourcent inconnue");
			}
		};
		
		if($simple = empty($pourcent[1]))
		{
			is_array($param) || $param = array($param);
			$pourcent = $pourcent[2];
			$fentrée = function($entrée) use($fchamp, $pourcent)
			{
				return $fchamp($pourcent, $entrée);
			};
		}
		else
		{
			$pourcent = explode(',', $pourcent[1]);
			$fentrée = function($entrée) use($fchamp, $pourcent)
			{
				$r = implode(',', array_map($fchamp, $pourcent, $entrée));
				$r = "($r)";
				return $r;
			};
		}
		
		return implode(',', array_map($fentrée, $param));
	}
}

?>
