<?php
class mysqlDB extends dbBase {
  var $db;
  var $prefix;
  var $config;

  function mysqlDB($config) {
    $this->prefix = $config['prefix'];
    $this->config = $config;
  }

  function init() {
  /* connect to the database */
    $this->db = mysqli_connect($this->config['host'],$this->config['user'],$this->config['password'],$this->config['name'])
        or die('ERROR: connection to database failed!');
  }


  function free() {
    mysqli_close($this->db);
  }

  function newId($tbl,$field = 'id',$keys = array ()) {
    $sql = 'SELECT max(`'.$field.'`) as newid FROM `'.$this->prefix.$tbl.'`';
    if (count($keys)>0) {
      $where = '';
      foreach ($keys as $k => $v) {
        if ($where != '') $where .= ' AND ';
        $where .= '`'.$k.'`=\''.(mysqli_real_escape_string($this->db,$v)).'\'';
      }
      $sql .= ' WHERE '.$where;
    }
    $res = mysqli_query($this->db,$sql);
    $newid = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    return $newid['newid']+1;
  }

  function newRandomId($tbl,$field = 'id',$size = 30, $alpha = false) {
    $found = true;
    while ($found) {
      $id = randomName($size,$size,$alpha);
      $sql = 'SELECT '.$field.' FROM `'.$this->prefix.$tbl.'` WHERE `'.$field.'`=\''.$id.'\'';
      $res = mysqli_query($this->db,$sql);
      if ($res) {
        $found = mysqli_num_rows($res)>0;
        mysqli_free_result($res);
      } else {
        $found = false;
      }
    }
    return $id;
  }

  function count($tbl,$keys = array()) {
    $sql = 'SELECT count(*) AS num FROM `'.$this->prefix.$tbl.'`';
    if (count($keys)>0) {
      $where = '';
      foreach ($keys as $k => $v) {
        if ($where != '') $where .= ' AND ';
        $where .= '`'.$k.'`=\''.(mysqli_real_escape_string($this->db,$v)).'\'';
      }
      $sql .= ' WHERE '.$where;
    }
    $res = mysqli_query($this->db,$sql);
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    return $row['num'];
  }

  function read($tbl,$keys = array(), $sort = array(), $limit = '', $assoc = array()) {
    $sql = 'SELECT * FROM `'.$this->prefix.$tbl.'`';
    if (count($keys)>0) {
      $where = '';
      foreach ($keys as $k => $v) {
        if ($where != '') $where .= ' AND ';
        $where .= '`'.$k.'`=\''.(mysqli_real_escape_string($this->db,$v)).'\'';
      }
      $sql .= ' WHERE '.$where;
    }
    if (count($sort)>0) {
      $sorting = '';
      foreach ($sort as $s) {
        if ($sorting!='') $sorting.=',';
        $sorting .= $s;
      }
      $sql .= ' ORDER BY '.$sorting;
    }
    if ($limit != '') {
      $sql .= ' LIMIT '.$limit;
    }
    $res = mysqli_query($this->db,$sql);
    if (!$res) { die('query failed: '.$sql); }
    $result = array();
    while ($row = mysqli_fetch_assoc($res)) {
      if (count($assoc)) { /* maybe there is a better way to do this? */
        $str = '$result';
        foreach ($assoc as $k) {
          $str .= '[\''.$row[$k].'\']';
        }
        $str .= '=$row;';
        eval($str);
      } else {
        $result[] = $row;
      }
    }
    mysqli_free_result($res);
    return $result;
  }

  /* This is an extended function which extends the select criteria */
  function readex($tbl,$criteria = array(), $sort = array(),$limit = '') {
    $sql = 'SELECT * FROM `'.$this->prefix.$tbl.'`';
    if (count($criteria)>0) {
      $where = '';
      foreach ($criteria as $ands) {
        $where_save = $where;
        $where = '';
        foreach ($ands as $v) {
          if ($where != '') $where .= ' AND ';
          $where .= '`'.$v[0].'`'.$v[1].'\''.(mysqli_real_escape_string($this->db,$v[2])).'\'';
        }
        if ($where_save!='') {
          $where = $where_save.' OR ('.$where.')';
        } else {
          $where = '('.$where.')';
        }
      }
      $sql .= ' WHERE '.$where;
    }
    if (count($sort)>0) {
      $sorting = '';
      foreach ($sort as $s) {
        if ($sorting!='') $sorting.=',';
        $sorting .= $s;
      }
      $sql .= ' ORDER BY '.$sorting;
    }
    if ($limit != '') {
      $sql .= ' LIMIT '.$limit;
    }
    $res = mysqli_query($this->db,$sql);
    if (!$res) { die('query failed: '.$sql); }
    $result = array();
    while ($row = mysqli_fetch_assoc($res)) {
      $result[] = $row;
    }
    mysqli_free_result($res);
    return $result;
  }

  function insert($tbl,$values,$fields = array()) {
    $sql = 'INSERT INTO `'.$this->prefix.$tbl.'`';
    $flist = '';
    $vlist = '';
    if (count($fields)>0) {
      foreach ($fields as $f) {
        if ($flist!='') $flist .= ',';
        if ($vlist!='') $vlist .= ',';
        $flist .= '`'.$f.'`';
        $vlist .= '\''.mysqli_real_escape_string($this->db,$values[$f]).'\'';
      }    
    } else {
      foreach ($values as $k => $v) {
        if ($flist!='') $flist .= ',';
        if ($vlist!='') $vlist .= ',';
        $flist .= '`'.$k.'`';
        $vlist .= '\''.mysqli_real_escape_string($this->db,$v).'\'';
      }
    }
    $sql .= ' ('.$flist.') VALUES ('.$vlist.')';
    return mysqli_query($this->db,$sql);
  }

  function update($tbl,$values,$keys = array(),$fields = array()) {
    $sql = 'UPDATE `'.$this->prefix.$tbl.'`';
    $set = '';
    if (count($fields)>0) {
      foreach ($fields as $f) {
        if ($set!='') $set .= ',';
        $set .= '`'.$f.'`=\''.mysqli_real_escape_string($this->db,$values[$f]).'\'';
      }    
    } else {
      foreach ($values as $k => $v) {
        if ($set!='') $set .= ',';
        $set .= '`'.$k.'`=\''.mysqli_real_escape_string($this->db,$v).'\'';
      }
    }
    $sql .= ' SET '.$set;
    if (count($keys)>0) { /* should always be */
      $where = '';
      foreach ($keys as $k => $v) {
        if ($where != '') $where .= ' AND ';
        $where .= '`'.$k.'`=\''.mysqli_real_escape_string($this->db,$v).'\'';
      }
      $sql .= ' WHERE '.$where;
    }
    return mysqli_query($this->db,$sql);
  }

  function delete($tbl,$keys = array()) {
    $sql = 'DELETE FROM `'.$this->prefix.$tbl.'`';
    if (count($keys)>0) {
      $where = '';
      foreach ($keys as $k => $v) {
        if ($where != '') $where .= ' AND ';
        $where .= '`'.$k.'`=\''.mysqli_real_escape_string($this->db,$v).'\'';
      }
      $sql .= ' WHERE '.$where;
    }
    return mysqli_query($this->db,$sql);
  }
}

?>
