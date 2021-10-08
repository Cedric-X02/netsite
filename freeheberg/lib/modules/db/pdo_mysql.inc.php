<?php
class pdo_mysqlDB extends dbBase {
  var $db;
  var $prefix;
  var $config;

  function pdo_mysqlDB($config) {
    $this->prefix = $config['prefix'];
    $this->config = $config;
  }

  function init() {
  /* connect to the database */
    $this->db = new PDO('mysql:host='.$this->config['host'].';dbname='.$this->config['name'],$this->config['user'],$this->config['password'])
        or die('ERROR: connection to database failed!');
    // TODO: verfy if this is necessary in production
    $this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
  }


  function free() {
    $this->db = null;
  }
  
  private function buildWhere($keys,$prefix)
  {
    $where = '';
    if (count($keys)>0) {
      foreach ($keys as $k => $v) {
        if ($where != '') $where .= ' AND ';
        $where .= '`'.$k.'`=:'.$k;
      }
      $where = $prefix.' '.$where;
    }
    return $where;
  }

  function newId($tbl,$field = 'id',$keys = array ()) {
    $sql = 'SELECT max(`'.$field.'`) as newid FROM `'.$this->prefix.$tbl.'`';
    $sql .= $this->buildWhere($keys,' WHERE ');
    $stmt = $this->db->prepare($sql);
    foreach ($keys as $k => $v) {
        $stmt->bindValue(':'.$k,$v);
    }
    $stmt-execute();
    return $stmt->fetchColumn()+1;
  }

  function newRandomId($tbl,$field = 'id',$size = 30, $alpha = false) {
    $found = true;
    $sql = 'SELECT '.$field.' FROM `'.$this->prefix.$tbl.'` WHERE `'.$field.'`=:id';
    $stmt = $this->db->prepare($sql);
    while ($found) {
      $id = randomName($size,$size,$alpha);
      $stmt->bindValue(':id',$id);
      $stmt->execute();
      if ($stmt->fetch()) {
        $found = true;
      } else {
        $found = false;
      }
    }
    return $id;
  }

  function count($tbl,$keys = array()) {
    $sql = 'SELECT count(*) AS num FROM `'.$this->prefix.$tbl.'`';
    $sql .= $this->buildWhere($keys,' WHERE ');
    $stmt = $this->db->prepare($sql);
    foreach ($keys as $k => $v) {
        $stmt->bindValue(':'.$k,$v);
    }
    $stmt->execute();
    return $stmt->fetchColumn();
  }

  function read($tbl,$keys = array(), $sort = array(), $limit = '', $assoc = array()) {
    $sql = 'SELECT * FROM `'.$this->prefix.$tbl.'`';
    $sql .= $this->buildWhere($keys,' WHERE ');
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
    $stmt = $this->db->prepare($sql);
    foreach ($keys as $k => $v) {
        $stmt->bindValue(':'.$k,$v);
    }
    $stmt->execute();
    $result = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
        foreach ($ands as $k=>$v) {
          if ($where != '') $where .= ' AND ';
          $where .= '`'.$v[0].'`'.$v[1].':f'.$k;
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
    $stmt = $this->db->prepare($sql);
    foreach ($keys as $k => $v) {
        $stmt->bindValue(':f'.$k,$v[2]);
    }
    $stmt->execute();
    $result = $stmt->fetchAll();
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
        $vlist .= ':'.$f;
      }    
    } else {
      foreach ($values as $k => $v) {
        if ($flist!='') $flist .= ',';
        if ($vlist!='') $vlist .= ',';
        $flist .= '`'.$k.'`';
        $vlist .= ':'.$k;
      }
    }
    $sql .= ' ('.$flist.') VALUES ('.$vlist.')';
    $stmt = $this->db->prepare($sql);
    if (count($fields)>0) {
      foreach ($fields as $f) {
        $stmt->bindValue(':'.$f,$values[$f]);
      }
    } else {
      foreach ($values as $k => $v) {
        $stmt->bindValue(':'.$k,$v);
      }
    }
    return $stmt->execute();
  }

  function update($tbl,$values,$keys = array(),$fields = array()) {
    $sql = 'UPDATE `'.$this->prefix.$tbl.'`';
    $set = '';
    if (count($fields)>0) {
      foreach ($fields as $f) {
        if ($set!='') $set .= ',';
        $set .= '`'.$f.'`=:f'.$f;
      }    
    } else {
      foreach ($values as $k => $v) {
        if ($set!='') $set .= ',';
        $set .= '`'.$k.'`=:f'.$k;
      }
    }
    $sql .= ' SET '.$set;
    $sql .= $this->buildWhere($keys,' WHERE ');
    $stmt = $this->db->prepare($sql);
    if (count($fields)>0) {
      foreach ($fields as $f) {
        $stmt->bindValue(':f'.$f,$values[$f]);
      }
    } else {
      foreach ($values as $k => $v) {
        $stmt->bindValue(':f'.$k,$v);
      }
    }
    foreach ($keys as $k => $v) {
        $stmt->bindValue(':'.$k,$v);
    }
    return $stmt->execute();
  }

  function delete($tbl,$keys = array()) {
    $sql = 'DELETE FROM `'.$this->prefix.$tbl.'`';
    $sql .= $this->buildWhere($keys,' WHERE ');
    $stmt = $this->db->prepare($sql);
    foreach ($keys as $k => $v) {
        $stmt->bindValue(':'.$k,$v);
    }
    return $stmt->execute();
  }
}

?>

