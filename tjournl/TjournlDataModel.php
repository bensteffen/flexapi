<?php

include_once __DIR__ . '/../datamodel/DataModel.php';
include_once __DIR__ . '/../datamodel/IdEntity.php';
include_once __DIR__ . '/../database/SqlConnection.php';
include_once __DIR__ . '/../accesscontrol/ACL/ACLGuard.php';

include_once __DIR__ . '/../../../analysis/gpxhandling.php';
include_once __DIR__ . '/../../utils/time.php';

$__ROOT__ = '../../';

function tjournl() {
    $dataModel = new DataModel();
    
    $dataModel->addEntities([
         new StageText()
        ,new ImageEdition()
        ,new Image()
        ,new Upload()
        ,new Track()
        ,new TrackBody()
        ,new Stage()
        ,new StageStatistic()
        ,new Tour()
        ,new TourStatistic()
        ,new Location()
        ,new StageCollection()
    ]);

    // references
    $dataModel->addReference([
        'referencingEntity' => 'tour',
        'referenceField'    => 'statistic',
        'referencedEntity'  => 'tourstatistic'
    ]);

    // ### stagecollection:

    // $dataModel->addReference([
    //     'direction'       => 'interactive',
    //     'entities'        => ['tour', 'stage'],
    //     'referenceFields' => ['id'  , 'id'],
    //     'name'            => 'stagecollection',
    // ]);
    // stagecollection -> tour
    // tour -> new entity tourinfo
    // -> transform interactive reference into 2 regular references:

    $dataModel->addReference([
        'referencingEntity' => 'stagecollection',
        'referenceField'    => 'tour',
        'referencedEntity'  => 'tour'
    ]);

    $dataModel->addReference([
        'referencingEntity' => 'stagecollection',
        'referenceField'    => 'stage',
        'referencedEntity'  => 'stage'
    ]);
    // ###################

    $dataModel->addReference([
        'referencingEntity' => 'track',
        'referenceField'    => 'from',
        'referencedEntity'  => 'location'
    ]);

    $dataModel->addReference([
        'referencingEntity' => 'track',
        'referenceField'    => 'to',
        'referencedEntity'  => 'location'
    ]);

    $dataModel->addReference([
        'referencingEntity' => 'track',
        'referenceField'    => 'gpsFile',
        'referencedEntity'  => 'upload'
    ]);

    $dataModel->addReference([
        'referencingEntity' => 'track',
        'referenceField'    => 'body',
        'referencedEntity'  => 'trackbody'
    ]);

    $dataModel->addReference([
        'referencingEntity' => 'stage',
        'referenceField'    => 'statistic',
        'referencedEntity'  => 'stagestatistic'
    ]);

    $dataModel->addReference([
        'direction'         => 'inverse',
        'referencingEntity' => 'stage',
        'containerField'    => 'tracks',
        'referencedEntity'  => 'track',
        'referenceField'    => 'stageId'
    ]);

    $dataModel->addReference([
        'direction'         => 'inverse',
        'referencingEntity' => 'stage',
        'containerField'    => 'images',
        'referencedEntity'  => 'image',
        'referenceField'    => 'stageId'
    ]);
    
    $dataModel->addReference([
        'direction'         => 'inverse',
        'referencingEntity' => 'image',
        'containerField'    => 'editions',
        'referencedEntity'  => 'imageedition',
        'referenceField'    => 'imageId'
    ]);

    $dataModel->addReference([
        'referencingEntity' => 'imageedition',
        'referenceField'    => 'file',
        'referencedEntity'  => 'upload'
    ]);

// ### observations ###
    $dataModel->addObservation([
        'observerName' => 'stage',
        'subjectName' => 'stagetext',
        'context' => ['onInsert', 'onDelete']
    ]);

    $dataModel->addObservation([
        'observerName' => 'stage',
        'subjectName' => 'track',
        'context' => ['onInsert', 'onDelete']
    ]);

    $dataModel->addObservation([
        'observerName' => 'image',
        'subjectName' => 'image',
        'context' => ['onInsert']
    ]);

    $dataModel->addObservation([
        'observerName' => 'stage',
        'subjectName' => 'image',
        'context' => ['onInsert', 'onDelete']
    ]);

    $dataModel->addObservation([
        'observerName' => 'stagestatistic',
        'subjectName' => 'stage',
        'context' => ['onInsert']
    ]);

    $dataModel->addObservation([
        'observerName' => 'track',
        'subjectName' => 'upload',
        'context' => ['onInsert', 'onDelete', 'onUpdate'],
        'triggerCondition' => function($event) {
            return array_key_exists('format', $event['data']) && $event['data']['format'] === 'gpx';
        }
    ]);

    $dataModel->addObservation([
        'observerName' => 'imageedition',
        'subjectName' => 'upload',
        'context' => ['onInsert'],
        'triggerCondition' => function($event) {
            return array_key_exists('format', $event['data']) && $event['data']['format'] === 'jpg';
        }
    ]);

    $dataModel->addObservation([
        'observerName' => 'tourstatistic',
        'subjectName' => 'tour',
        'context' => ['onInsert', 'onDelete']
    ]);

    $dataModel->addObservation([
        'observerName' => 'tourstatistic',
        'subjectName' => 'stagecollection',
        'context' => ['onInsert', 'onUpdate' , 'onDelete']
    ]);
    
    $dataModel->addObservation([
        'observerName' => 'upload',
        'subjectName' => 'upload',
        'context' => ['onInsert', 'onDelete']
    ]);
// ####################

    return $dataModel;
}

class StageCollection extends IdEntity {
    public function __construct() {
        parent::__construct('stagecollection');
        $this->addFields([
            ['name' => 'tour' , 'type' => 'int'],
            ['name' => 'stage', 'type' => 'int']
        ]);
    }
}

class Tour extends IdEntity {
    public function __construct() {
        parent::__construct('tour');
        $this->addFields([
            ['name' => 'title'    , 'type' => 'varchar', 'length' => 128],
            ['name' => 'statistic', 'type' => 'int']
        ]);
    }
}

class Statistic extends IdEntity {
    public function __construct($entityName) {
        parent::__construct($entityName);
        $this->addFields([
            ['name' => 'distance', 'type' => 'decimal', 'length' => '6,1'], // [km]
            ['name' => 'duration', 'type' => 'int'], // [seconds]
            ['name' => 'speed'   , 'type' => 'decimal', 'length' => '4,1'], // [km/h]
            ['name' => 'ascend'  , 'type' => 'int'], // [meters]
            ['name' => 'descend' , 'type' => 'int']   // [meters]
        ]);
    }

    public static function newStats() {
        return [
            'id'       => null,
            'distance' => 0,
            'duration' => 0,
            'speed'    => 0,
            'ascend'   => 0,
            'descend'  => 0
        ];
    }
}

class TourStatistic extends Statistic {
    public function __construct() {
        parent::__construct('tourstatistic');
    }

    public function observationUpdate($event) {
        if ($event['subjectName'] === 'stagecollection') {
            if ($event['context'] === 'onInsert') {
                $tour = $this->dataModel->read('tour', [
                    'references' => ['format' => 'key'],
                    'filter' => ['id' => $event['data']['tour']],
                    'flatten' => true
                ]);
                if ($tour === null) {
                    throw(new Exception("Tour with id = ".$event['data']['tour']." not found.", 400));
                }
                $this->updateTourStatistic($tour);
            } elseif ($event['context'] === 'onDelete') {
                $tours =  $this->dataModel->read('tour', [
                    'references' => ['format' => 'key']
                ]);
                if ($tours !== null) {
                    foreach($tours as $tour) {
                        $this->updateTourStatistic($tour);
                    }
                }
            }
        } elseif ($event['subjectName'] === 'tour') {
            $statId = $this->dataModel->insert('tourstatistic');
            if ($statId) {
                $this->dataModel->update('tour', [
                    'id' => $event['insertId'],
                    'statistic' => $statId
                ]);
            }
        }
    }

    protected function updateTourStatistic($tourData) {
        $statistic = $this->dataModel->read('tourstatistic', [
            'filter' => ['id' => $tourData['statistic']],
            'flatten' => true
        ]);
        $stages = $this->dataModel->read('stagecollection', [
            'references' => [ 'depth' => 2, 'format' => 'data'],
            'selection' => ['stage'],
            'filter' => ['tour' => $tourData['id']]
        ]);
        $statistic['distance'] = 0;
        $statistic['duration'] = 0;
        $statistic['speed']    = null;
        $statistic['ascend']   = 0;
        $statistic['descend']  = 0;
        foreach($stages as $stageData) {
            $statistic['distance'] += array_sum(extractByKey('distance', $stageData['stage']['tracks']));
            $durations = array_map(function($t) {
                return strtotime("1970-01-01+00:00 ".$t);
            }
            , extractByKey('duration', $stageData['stage']['tracks']));
            $statistic['duration'] += array_sum($durations);
            $statistic['ascend']   += array_sum(extractByKey('ascend', $stageData['stage']['tracks']));
            $statistic['descend']  += array_sum(extractByKey('descend', $stageData['stage']['tracks']));

        }
        $statistic['speed'] = $statistic['distance']/($statistic['duration']/3600);
        $this->dataModel->update('tourstatistic', $statistic);
    }
}

class Stage extends IdEntity {
    public function __construct() {
        parent::__construct('stage');
        $this->addField(['name' => 'date'     , 'type' => 'date']);
        $this->addField(['name' => 'statistic', 'type' => 'int']);
    }

    public function observationUpdate($event) {
        if ($event['context'] === 'onInsert') {
            switch ($event['subjectName']) {
                case 'track':
                    $date = new DateTime($event['data']['start']);
                    $date = $date->format('Y-m-d');
                    break;
                case 'image':
                    $date = new DateTime($event['data']['time']);
                    $date = $date->format('Y-m-d');
                    break;
                case 'stagetext':
                    $date =  $event['data']['date'];
                    break;
                default:
                    throw(new Exception("Cannot assign entity '".$event['subjectName']."' to a stage.", 400));
            }
            $stageId = $this->date2StageId($date);
            $this->dataModel->update($event['subjectName'], [
                'id' => $event['insertId'],
                'stageId' => $stageId
            ]);
            switch ($event['subjectName']) {
                case 'track':
                    $this->updateStageStatistic($stageId);
                    break;
                case 'image':
                    $this->estimateCoordinatesIfMissing($stageId);
                    break;
            }
        }
    }

    protected function date2StageId($date) {
        $stage = $this->dataModel->read('stage', [
            'filter' => ['date' => $date ],
            'selection' => ['id'],
            'flatten' => true
        ]);
        if ($stage === null) {
            $stageId = $this->dataModel->insert('stage', ['date' => $date]);
        } else {
            $stageId = $stage['id'];
        }
        return $stageId;
    }

    protected function updateStageStatistic($stageId) {
        $stageData = $this->dataModel->read('stage', [
            'filter' => ['id' => $stageId],
            'selection' => ['id', 'statistic', 'tracks'],
            'references' => ['format' => 'data', 'depth' => 1],
            'flatten' => true
        ]);
        $tracks = $stageData['tracks'];
        $statistic = $stageData['statistic'];
        $statistic['distance'] = array_sum(extractByKey('distance', $tracks));
        $durationVec = array_map(function($hmsStr) {
            return hms2seconds($hmsStr);
        } ,extractByKey('duration', $tracks));
        $statistic['duration'] = array_sum($durationVec);
        if ($statistic['duration'] > 0) {
            $statistic['speed'] = $statistic['distance']/($statistic['duration']/3600);
        }
        $statistic['ascend']   = array_sum(extractByKey('ascend'  , $tracks));
        $statistic['descend']  = array_sum(extractByKey('descend' , $tracks));
        $this->dataModel->update('stagestatistic', $statistic);
    }

    protected function estimateCoordinatesIfMissing($stageId) {
        $tracks = $this->dataModel->read('track', [
            'filter'     => ['stageId' => $stageId],
            'references' => ['format' => 'data', 'depth' => 'deep'],
            'selection'  => ['body', 'timeZone']
        ]);
        $images = $this->dataModel->read('image', [
            'filter' => ['stageId' => $stageId]
        ]);
        foreach ($images as $image) {
            if ($image['coordinateAssignment'] === 'not_assigned') {
                foreach ($tracks as $track) {
                    $timeZone = new DateTimeZone($track['timeZone']);
                    $elementTime = new DateTime($image['time'], $timeZone);
                    $elementTimeStamp = $elementTime->getTimeStamp();
                    
                    $lat = lininterp($track['body']['time'], $track['body']['lat'], $elementTimeStamp);
                    $lon = lininterp($track['body']['time'], $track['body']['lon'], $elementTimeStamp);
                    if ($lat !== null && $lon !== null) {
                        $image['lat'] = $lat;
                        $image['lon'] = $lon;
                        $image['coordinateAssignment'] = 'gps_track';
                        $this->dataModel->update('image', $image);
                        return;
                    }
                }
            }
        }
    }
}

class StageStatistic extends Statistic {
    public function __construct() {
        parent::__construct('stagestatistic');
    }

    public function observationUpdate($event) {
        if ($event['subjectName'] === 'stage' && $event['context'] === 'onInsert') {
            $statId = $this->dataModel->insert('stagestatistic');
            if ($statId) {
                $this->dataModel->update('stage', [
                    'id' => $event['insertId'],
                    'statistic' => $statId
                ]);
            }
        }
    }
}

class StageText extends IdEntity {
    public function __construct() {
        parent::__construct('stagetext');
        $this->addFields([
            ['name' => 'date'   , 'type' => 'date'],
            ['name' => 'stageId', 'type' => 'int'],
            ['name' => 'teaser' , 'type' => 'varchar', 'length' => 512],
            ['name' => 'content', 'type' => 'text']
        ]);
    }
}

class Image extends IdEntity {
    public function __construct() {
        parent::__construct('image');
        $this->addFields([
            ['name' => 'time'                , 'type' => 'datetime'],
            ['name' => 'stageId'             , 'type' => 'int'],
            ['name' => 'lat'                 , 'type' => 'decimal', 'length' => '8,6', 'notNull' => false],
            ['name' => 'lon'                 , 'type' => 'decimal', 'length' => '9,6', 'notNull' => false],
            ['name' => 'coordinateAssignment', 'type' => 'varchar', 'length' => 32],
        ]);
    }

    public function observationUpdate($event) {
        if ($event['subjectName'] === 'image') {
            $data = $this->dataModel->read('image', [
                'filter' => ['id' => $event['insertId']],
                'flatten' => true
            ]);
            $updateData = ['id' => $data['id']];
            if ($data['lat'] === null || $data['lon'] === null) {
                $updateData['coordinateAssignment'] = 'not_assigned';
            } else {
                $updateData['coordinateAssignment'] = 'native';
            }
            $this->dataModel->update('image', $updateData);
        }
    }
}

class ImageEdition extends IdEntity {
    public function __construct() {
        parent::__construct('imageedition');
        $this->addFields([
            ['name' => 'imageId' , 'type' => 'int'],
            ['name' => 'edition' , 'type' => 'varchar', 'length' => 16],
            ['name' => 'file'    , 'type' => 'int']
        ]);
    }

    public function observationUpdate($event) {
        $metaData = $event['metaData'];
        if (!array_key_exists('imageId', $metaData) || !array_key_exists('edition', $metaData)) {
            throw(new Exception("Could not upload image edition due to missing imageId and/or edition", 400));
        }
        $this->dataModel->insert('imageedition', [
            'imageId' => $metaData['imageId'],
            'edition' => $metaData['edition'],
            'file' => $event['insertId'],
        ]);
    }
}

class Track extends IdEntity {
    public function __construct() {
        parent::__construct('track');
        $this->addFields([
            ['name' => 'start'   , 'type' => 'datetime'],
            ['name' => 'stop'    , 'type' => 'datetime'],
            ['name' => 'timeZone', 'type' => 'varchar', 'length' => 32],
            ['name' => 'from'    , 'type' => 'int'],
            ['name' => 'to'      , 'type' => 'int'],
            ['name' => 'gpsFile' , 'type' => 'int'],
            ['name' => 'stageId' , 'type' => 'int'],
            ['name' => 'duration', 'type' => 'varchar', 'length' => 16],
            ['name' => 'distance', 'type' => 'decimal', 'length' => "4,1"],
            ['name' => 'speed'   , 'type' => 'decimal', 'length' => "3,1"],
            ['name' => 'ascend'  , 'type' => 'decimal', 'length' => "5,0"],
            ['name' => 'descend' , 'type' => 'decimal', 'length' => "5,0"],
            ['name' => 'body'    , 'type' => 'int']
        ]);
    }

    public function observationUpdate($event) {
        if ($event['context'] === 'onInsert') {
            $file = $event['data'];
            switch ($file['format']) {
                case 'gpx':
                    $gpx = readGpxFile($file['source']);
                    $gps = analyzeGpx($gpx, $event['metaData']);
                    // $gps = analyzeGpx($gpx, $event['metaData'], true);
                    break;
                default:
                    throw(new Exception("Cannot analyze GPS file format '".$file['format']."'", 400));
            }
            $fromFilter = ['lat' => $gps['origin']['lat'], 'lon' => $gps['origin']['lon']];
            $fromId = $this->dataModel->upsert('location', $fromFilter, $gps['origin']);

            $toFilter = ['lat' => $gps['destination']['lat'], 'lon' => $gps['destination']['lon']];
            $toId = $this->dataModel->upsert('location', $toFilter, $gps['destination']);

            $bodyId = $this->dataModel->insert('trackbody', [
                'lat'   => $gps['lat'],
                'lon'   => $gps['lon'],
                'x'     => $gps['x'],
                't'     => $gps['t'],
                'time'  => $gps['timeStamps'],
                'z'     => $gps['z'],
                'speed' => $gps['speed'],
            ]);
            $this->dataModel->insert('track', [
                'start'    => $gps['start'],
                'stop'     => $gps['stop'],
                'timeZone' => $gps['time_zone'],
                'from'     => $fromId,
                'to'       => $toId,
                'gpsFile'  => $event['insertId'],
                'duration' => $gps['duration'],
                'distance' => $gps['distance'],
                'speed'    => $gps['avgspeed'],
                'ascend'   => $gps['ascend'],
                'descend'  => $gps['descend'],
                'body'     => $bodyId
            ]);
        }
    }
}

class TrackBody extends IdEntity {
    public function __construct() {
        parent::__construct('trackbody');
        $this->addFields([
            ['name' => 'lat'  , 'type' => 'object'],
            ['name' => 'lon'  , 'type' => 'object'],
            ['name' => 'x'    , 'type' => 'object'],
            ['name' => 't'    , 'type' => 'object'],
            ['name' => 'time' , 'type' => 'object'],
            ['name' => 'speed', 'type' => 'object'],
            ['name' => 'z'    , 'type' => 'object']
        ]);
    }
}

class Upload extends IdEntity {
    public function __construct() {
        parent::__construct('upload');
        $this->addFields([
            ['name' => 'name'  , 'type' => 'varchar', 'length' => 64],
            ['name' => 'format', 'type' => 'varchar', 'length' => 16],
            ['name' => 'mimeType', 'type' => 'varchar', 'length' => 16],
            ['name' => 'source'  , 'type' => 'text']
        ]);
    }

    public function observationUpdate($event) {
        if ($event['context'] === 'onInsert') {
            $mimeType = "";
            $base64 = false;
            $data = $event['data'];
            switch ($data['format']) {
                case 'gpx':
                    $mimeType = "text/xml";
                    break;
                case 'jpg':
                    $mimeType = "image/jpeg";
                    $base64 = true;
                    break;
            }
    
            $source = $data['source'];
            $filPath = $GLOBALS['__ROOT__'].$source;
            if (filesize($filPath) < 65536 - 22 - 4) {
                $file = file_get_contents($filPath);
                if ($base64) {
                    $source = 'data:'.$mimeType.';base64,'.base64_encode($file);
                } else {
                    $source = 'data:'.$mimeType.','.$file;
                }
                unlink($filPath);
            }
    
            $data['id'] = $event['insertId'];
            $data['mimeType'] = $mimeType;
            $data['source'] = $source;
    
            $this->dataModel->update('upload', $data);
        }
        if ($event['context'] === 'onDelete') {
            foreach($event['data'] as $data) {
                $source = $data['source'];
                if(!stringStart($source, 'data:')) {
                    unlink($GLOBALS['__ROOT__'].$source);
                }
            }
        }
    }
}

class Location extends IdEntity {
    public function __construct() {
        parent::__construct('location');
        $this->addFields([
            ['name' => 'lat'     , 'type' => 'decimal', 'length' => '8,6', 'notNull' => false],
            ['name' => 'lon'     , 'type' => 'decimal', 'length' => '9,6', 'notNull' => false],
            ['name' => 'name'    , 'type' => 'varchar', 'length' => 64],
            ['name' => 'district', 'type' => 'varchar', 'length' => 64],
            ['name' => 'country' , 'type' => 'varchar', 'length' => 64],
        ]);
    }
}

?>