<?php
namespace App\Services;

use Config, File, Log, Carbon, Storage;
use \Aws\MediaConvert\MediaConvertClient;
use \Aws\Exception\AwsException;


/*
 * Refernece -  https://github.com/aws-samples/aws-media-services-simple-vod-workflow/tree/master/2-MediaConvertJobs
 */

class AwsMediaConvert
{


    protected $mediaConvertClient;



    public function __construct()
    {
//        parent::__construct();

        $ACCOUNT_ENDPOINT = "https://yz72y1um.mediaconvert.ap-southeast-1.amazonaws.com";

        $this->mediaConvertClient = new MediaConvertClient([
            'credentials' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY')],
            'version' => '2017-08-29',
            'region' => 'ap-southeast-1',
            'endpoint' => $ACCOUNT_ENDPOINT
        ]);

    }


    public function createHlsVodJob($params = array())
    {

        $error_messages = $results = [];

        try {

            $s3_input_path      =   (isset($params['s3_input_path'])) ? trim($params['s3_input_path']) : "";
            $s3_output_path     =   (isset($params['s3_output_path'])) ? trim($params['s3_output_path']) : "";
            $orientation        =   (isset($params['orientation'])) ? trim($params['orientation']) : "landscape";

            if ($s3_input_path != '' && $s3_output_path != '') {

                $params = [
                    's3_input_path' => $s3_input_path,
                    's3_output_path' => $s3_output_path,
                    'orientation'       => $orientation
                ];

                $jobSettings = $this->getJobSetting($params);

                $create_job_result = $this->mediaConvertClient->createJob([
                    "Role" => "arn:aws:iam::337477240173:role/mediaconvert",
                    "Queue" => "arn:aws:mediaconvert:ap-southeast-1:337477240173:queues/Default",
                    "UserMetadata" => [
                        "Customer" => "ARMS"
                    ],
                    "Settings" => $jobSettings, //JobSettings structure
                ]);

                $jobData = $create_job_result->get('Job');
                $job_id = $jobData['Id'];
                $job_status = strtolower($jobData['Status']);

                $results = [
                    'job_data' => $jobData, 'job_id' => $job_id, 'job_status' => $job_status, 'error' => false
                ];

//                $results            = $create_job_result;
//                $results['error']   = false;

            } else {

                $error_messages = ['error' => true, 'message' => 's3_input_path or s3_output_path cannot be blank'];

            }

        } catch (AwsException $e) {

            // output error message if fails
            // echo $e->getMessage(); echo "\n";
            $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('MediaConvert - createHlsVodJob  : Fail ', $error_messages);

        }

        return ['error_messages' => $error_messages, 'results' => $results];

    }


    public function getJobSetting($params)
    {

        $s3_input_path      =   trim($params['s3_input_path']);
        $s3_output_path     =   trim($params['s3_output_path']);

        $AdAvailOffset      =   0;

        $Inputs             =   [
            [
                "AudioSelectors" => [
                    "Audio Selector 1" => [
                        "Offset" => 0,
                        "DefaultSelection" => "NOT_DEFAULT",
                        "ProgramSelection" => 1,
                        "SelectorType" => "TRACK",
                        "Tracks" => [1]
                    ]
                ],
                "VideoSelector" => [
                    "ColorSpace" => "FOLLOW"
                ],
                "FilterEnable" => "AUTO",
                "PsiControl" => "USE_PSI",
                "FilterStrength" => 0,
                "DeblockFilter" => "DISABLED",
                "DenoiseFilter" => "DISABLED",
                "TimecodeSource" => "EMBEDDED",
                "FileInput" => $s3_input_path
            ]
        ];


        $vod_720p           =   $this->get720pSetting($params);
        $vod_480p           =   $this->get480pSetting($params);
        $vod_360p           =   $this->get360pSetting($params);
        $vod_270p           =   $this->get270pSetting($params);
        $vod_144p           =   $this->get144pSetting($params);

//        $ProfileOutputs     =   [$vod_720p, $vod_480p, $vod_360p, $vod_270p, $vod_144p];
        $ProfileOutputs     =   [$vod_720p, $vod_480p, $vod_360p, $vod_270p];
//        $ProfileOutputs     =   [$vod_720p];

        $ThumbnailOutPuts   =   [
            [
                "ContainerSettings" => [
                    "Container" => "RAW"
                ],
                "VideoDescription" => [
                    "Width" => 1280,
                    "ScalingBehavior" => "DEFAULT",
                    "Height" => 720,
                    "TimecodeInsertion" => "DISABLED",
                    "AntiAlias" => "ENABLED",
                    "Sharpness" => 50,
                    "CodecSettings" => [
                        "Codec" => "FRAME_CAPTURE",
                        "FrameCaptureSettings" => [
                            "FramerateNumerator" => 1,
                            "FramerateDenominator" => 10,
                            "MaxCaptures" => 10,
                            "Quality" => 80
                        ]
                    ],
                    "AfdSignaling" => "NONE",
                    "DropFrameTimecode" => "ENABLED",
                    "RespondToAfd" => "NONE",
                    "ColorMetadata" => "INSERT"
                ]
            ]
        ];

        $OutputGroups       =   [
            [
                "CustomName" => "VideoProfilesInHLS",
                "Name" => "Apple HLS",
                "OutputGroupSettings" => [
                    "Type" => "HLS_GROUP_SETTINGS",
                    "HlsGroupSettings" => [
                        "ManifestDurationFormat" => "INTEGER",
                        "SegmentLength" => 10,
                        "TimedMetadataId3Period" => 10,
                        "CaptionLanguageSetting" => "OMIT",
                        "Destination" => $s3_output_path,
                        "TimedMetadataId3Frame" => "PRIV",
                        "CodecSpecification" => "RFC_4281",
                        "OutputSelection" => "MANIFESTS_AND_SEGMENTS",
                        "ProgramDateTimePeriod" => 600,
                        "MinSegmentLength" => 0,
                        "MinFinalSegmentLength" => 0,
                        "DirectoryStructure" => "SINGLE_DIRECTORY",
                        "ProgramDateTime" => "EXCLUDE",
                        "SegmentControl" => "SEGMENTED_FILES",
                        "ManifestCompression" => "NONE",
                        "ClientCache" => "ENABLED",
                        "StreamInfResolution" => "INCLUDE"
                    ]
                ],
                "Outputs" => $ProfileOutputs
            ],
            [
                "CustomName" => "VideoThumbnail",
                "Name" => "File Group",
                "OutputGroupSettings" => [
                    "Type" => "FILE_GROUP_SETTINGS",
                    "FileGroupSettings" => [
                        "Destination" => $s3_output_path,
                    ]
                ],
                "Outputs" => $ThumbnailOutPuts
            ]

        ];



        $jobSetting = [
            "TimecodeConfig" => [
                "Source" => "EMBEDDED"
            ],
            "AdAvailOffset" => $AdAvailOffset,
            "Inputs" => $Inputs,
            "OutputGroups" => $OutputGroups,
        ];


        return $jobSetting;

    }


    public function get720pSetting($params = array())
    {

        $orientation    =   (isset($params['orientation'])) ? trim($params['orientation']) : "landscape";
        $width          =   ($orientation == "landscape") ? 1280 : 720;
        $height         =   ($orientation == "landscape") ? 720  : 1280;

//            "Preset" => "System-Ott_Hls_Ts_Avc_Aac_16x9_1280x720p_30Hz_3.5Mbps",

        $setting = [
            "ContainerSettings" => [
                "Container" => "M3U8",
                "M3u8Settings" => [
                    "AudioFramesPerPes" => 4,
                    "PcrControl" => "PCR_EVERY_PES_PACKET",
                    "PmtPid" => 480,
                    "PrivateMetadataPid" => 503,
                    "ProgramNumber" => 1,
                    "PatInterval" => 0,
                    "PmtInterval" => 0,
                    "VideoPid" => 481,
                    "AudioPids" => [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],

            "VideoDescription" => [
                "Width" => $width,
                "ScalingBehavior" => "DEFAULT",
                "Height" => $height,
                "VideoPreprocessors" => [
                    "Deinterlacer" => [
                        "Algorithm" => "INTERPOLATE",
                        "Mode" => "DEINTERLACE",
                        "Control" => "NORMAL"
                    ]
                ],
                "TimecodeInsertion" => "DISABLED",
                "AntiAlias" => "ENABLED",
                "Sharpness" => 50,
                "CodecSettings" => [
                    "Codec" => "H_264",
                    "H264Settings" => [
                        "InterlaceMode" => "PROGRESSIVE",
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 90,
                        "Slices" => 1,
                        "GopBReference" => "ENABLED",
                        "HrdBufferSize" => 7000000,
                        "SlowPal" => "DISABLED",
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "ENABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 3500000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "HIGH",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 30000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "HIGH",
                        "CodecLevel" => "LEVEL_4",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "INITIALIZE_FROM_SOURCE",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling" => "NONE",
                "DropFrameTimecode" => "ENABLED",
                "RespondToAfd" => "NONE",
                "ColorMetadata" => "INSERT"
            ],
            "AudioDescriptions" => [
                [
                    "AudioTypeControl" => "FOLLOW_INPUT",
                    "AudioSourceName" => "Audio Selector 1",
                    "CodecSettings" => [
                        "Codec" => "AAC",
                        "AacSettings" => [
                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                            "Bitrate" => 96000,
                            "RateControlMode" => "CBR",
                            "CodecProfile" => "HEV1",
                            "CodingMode" => "CODING_MODE_2_0",
                            "RawFormat" => "NONE",
                            "SampleRate" => 48000,
                            "Specification" => "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl" => "FOLLOW_INPUT",
                    "AudioType" => 0
                ]
            ],
            "NameModifier" => "720p",
            "OutputSettings" => [
                "HlsSettings" => [
                    "SegmentModifier" => '$dt$'
                ]
            ]

        ];

        return $setting;
    }



    public function get720pSettingBk($params = array())
    {

        $orientation    =   (isset($params['orientation'])) ? trim($params['orientation']) : "landscape";
        $width          =   ($orientation == "landscape") ? 1280 : 720;
        $height         =   ($orientation == "landscape") ? 720  : 1280;

//            "Preset" => "System-Ott_Hls_Ts_Avc_Aac_16x9_1280x720p_30Hz_3.5Mbps",

        $setting = [
            "ContainerSettings" => [
                "Container" => "M3U8",
                "M3u8Settings" => [
                    "AudioFramesPerPes" => 4,
                    "PcrControl" => "PCR_EVERY_PES_PACKET",
                    "PmtPid" => 480,
                    "PrivateMetadataPid" => 503,
                    "ProgramNumber" => 1,
                    "PatInterval" => 0,
                    "PmtInterval" => 0,
                    "VideoPid" => 481,
                    "AudioPids" => [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],

            "VideoDescription" => [
                "Width" => $width,
                "ScalingBehavior" => "DEFAULT",
                "Height" => $height,
                "VideoPreprocessors" => [
                    "Deinterlacer" => [
                        "Algorithm" => "INTERPOLATE",
                        "Mode" => "DEINTERLACE",
                        "Control" => "NORMAL"
                    ]
                ],
                "TimecodeInsertion" => "DISABLED",
                "AntiAlias" => "ENABLED",
                "Sharpness" => 50,
                "CodecSettings" => [
                    "Codec" => "H_264",
                    "H264Settings" => [
                        "InterlaceMode" => "PROGRESSIVE",
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 90,
                        "Slices" => 1,
                        "GopBReference" => "ENABLED",
                        "HrdBufferSize" => 7000000,
                        "SlowPal" => "DISABLED",
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "ENABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 3500000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "HIGH",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 30000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "HIGH",
                        "CodecLevel" => "LEVEL_4",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "INITIALIZE_FROM_SOURCE",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling" => "NONE",
                "DropFrameTimecode" => "ENABLED",
                "RespondToAfd" => "NONE",
                "ColorMetadata" => "INSERT"
            ],
            "AudioDescriptions" => [
                [
                    "AudioTypeControl" => "FOLLOW_INPUT",
                    "AudioSourceName" => "Audio Selector 1",
                    "CodecSettings" => [
                        "Codec" => "AAC",
                        "AacSettings" => [
                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                            "Bitrate" => 96000,
                            "RateControlMode" => "CBR",
                            "CodecProfile" => "HEV1",
                            "CodingMode" => "CODING_MODE_2_0",
                            "RawFormat" => "NONE",
                            "SampleRate" => 48000,
                            "Specification" => "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl" => "FOLLOW_INPUT",
                    "AudioType" => 0
                ]
            ],
            "NameModifier" => "720p",
            "OutputSettings" => [
                "HlsSettings" => [
                    "SegmentModifier" => '$dt$'
                ]
            ]

        ];

        return $setting;
    }


    public function get480pSetting($params = array())
    {

        $orientation = (isset($params['orientation'])) ? trim($params['orientation']) : "landscape";
        $width                  =  ($orientation == "landscape") ? 640 : 480;
        $height                 =  ($orientation == "landscape") ? 480  : 640;
        $ScalingBehavior        =  ($orientation == "landscape") ? "DEFAULT"  : "STRETCH_TO_OUTPUT";

        //"Preset" => "System-Avc_4x3_480p_29_97fps_600kbps",


        $setting = [
            "ContainerSettings" => [
                "Container" => "M3U8",
                "M3u8Settings" => [
                    "AudioFramesPerPes" => 4,
                    "PcrControl" => "PCR_EVERY_PES_PACKET",
                    "PmtPid" => 480,
                    "PrivateMetadataPid" => 503,
                    "ProgramNumber" => 1,
                    "PatInterval" => 0,
                    "PmtInterval" => 0,
                    "VideoPid" => 481,
                    "AudioPids" => [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "VideoDescription" => [
                "Width" => $width,
                "ScalingBehavior" => $ScalingBehavior,
                "Height" => $height,
                "VideoPreprocessors" => [
                    "Deinterlacer" => [
                        "Algorithm" => "INTERPOLATE",
                        "Mode" => "DEINTERLACE",
                        "Control" => "NORMAL"
                    ]
                ],
                "TimecodeInsertion" => "DISABLED",
                "AntiAlias" => "ENABLED",
                "Sharpness" => 50,
                "CodecSettings" => [
                    "Codec" => "H_264",
                    "H264Settings" => [
                        "InterlaceMode" => "PROGRESSIVE",
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 90,
                        "Slices" => 1,
                        "GopBReference" => "DISABLED",
                        "HrdBufferSize" => 900000,
                        "SlowPal" => "DISABLED",
                        "ParDenominator" => 1,
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "DISABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 600000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "MAIN",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 30000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "HIGH",
                        "CodecLevel" => "LEVEL_3_1",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling" => "NONE",
                "DropFrameTimecode" => "ENABLED",
                "RespondToAfd" => "NONE",
                "ColorMetadata" => "INSERT"
            ],
            "AudioDescriptions" => [
                [
                    "AudioTypeControl" => "FOLLOW_INPUT",
                    "AudioSourceName" => "Audio Selector 1",
                    "CodecSettings" => [
                        "Codec" => "AAC",
                        "AacSettings" => [
                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                            "Bitrate" => 64000,
                            "RateControlMode" => "CBR",
                            "CodecProfile" => "HEV1",
                            "CodingMode" => "CODING_MODE_2_0",
                            "RawFormat" => "NONE",
                            "SampleRate" => 48000,
                            "Specification" => "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl" => "FOLLOW_INPUT",
                    "AudioType" => 0
                ]
            ],

            "NameModifier" => "480p",
            "OutputSettings" => [
                "HlsSettings" => [
                    "SegmentModifier" => '$dt$'
                ]
            ]

        ];

        return $setting;
    }


    public function get360pSetting($params = array())
    {

        $orientation = (isset($params['orientation'])) ? trim($params['orientation']) : "landscape";
        $width      =  ($orientation == "landscape") ? 640 : 360;
        $height     =  ($orientation == "landscape") ? 360  : 640;

        //"Preset" => "System-Ott_Hls_Ts_Avc_Aac_16x9_640x360p_30Hz_0.6Mbps",

        $setting = [
            "ContainerSettings" => [
                "Container" => "M3U8",
                "M3u8Settings" => [
                    "AudioFramesPerPes" => 4,
                    "PcrControl" => "PCR_EVERY_PES_PACKET",
                    "PmtPid" => 480,
                    "PrivateMetadataPid" => 503,
                    "ProgramNumber" => 1,
                    "PatInterval" => 0,
                    "PmtInterval" => 0,
                    "VideoPid" => 481,
                    "AudioPids" => [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "VideoDescription" => [
                "Width" => $width,
                "ScalingBehavior" => "DEFAULT",
                "Height" => $height,
                "VideoPreprocessors" => [
                    "Deinterlacer" => [
                        "Algorithm" => "INTERPOLATE",
                        "Mode" => "DEINTERLACE",
                        "Control" => "NORMAL"
                    ]
                ],
                "TimecodeInsertion" => "DISABLED",
                "AntiAlias" => "ENABLED",
                "Sharpness" => 50,
                "CodecSettings" => [
                    "Codec" => "H_264",
                    "H264Settings" => [
                        "InterlaceMode" => "PROGRESSIVE",
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 90,
                        "Slices" => 1,
                        "GopBReference" => "ENABLED",
                        "HrdBufferSize" => 1200000,
                        "SlowPal" => "DISABLED",
                        "ParDenominator" => 1,
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "ENABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 600000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "MAIN",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 30000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "MEDIUM",
                        "CodecLevel" => "LEVEL_3_1",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling" => "NONE",
                "DropFrameTimecode" => "ENABLED",
                "RespondToAfd" => "NONE",
                "ColorMetadata" => "INSERT"
            ],
            "AudioDescriptions" => [
                [
                    "AudioTypeControl" => "FOLLOW_INPUT",
                    "AudioSourceName" => "Audio Selector 1",
                    "CodecSettings" => [
                        "Codec" => "AAC",
                        "AacSettings" => [
                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                            "Bitrate" => 64000,
                            "RateControlMode" => "CBR",
                            "CodecProfile" => "HEV1",
                            "CodingMode" => "CODING_MODE_2_0",
                            "RawFormat" => "NONE",
                            "SampleRate" => 48000,
                            "Specification" => "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl" => "FOLLOW_INPUT",
                    "AudioType" => 0
                ]
            ],


            "NameModifier" => "360p",
            "OutputSettings" => [
                "HlsSettings" => [
                    "SegmentModifier" => '$dt$'
                ]
            ]

        ];

        return $setting;
    }


    public function get270pSetting($params = array())
    {

        $orientation = (isset($params['orientation'])) ? trim($params['orientation']) : "landscape";
        $width      =  ($orientation == "landscape") ? 480 : 270;
        $height     =  ($orientation == "landscape") ? 270  : 480;


        //"Preset" => "System-Ott_Hls_Ts_Avc_Aac_16x9_480x270p_15Hz_0.4Mbps",
        $setting = [
            "ContainerSettings" => [
                "Container" => "M3U8",
                "M3u8Settings" => [
                    "AudioFramesPerPes" => 4,
                    "PcrControl" => "PCR_EVERY_PES_PACKET",
                    "PmtPid" => 480,
                    "PrivateMetadataPid" => 503,
                    "ProgramNumber" => 1,
                    "PatInterval" => 0,
                    "PmtInterval" => 0,
                    "VideoPid" => 481,
                    "AudioPids" => [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "VideoDescription" => [
                "Width" => $width,
                "ScalingBehavior" => "DEFAULT",
                "Height" => $height,
                "VideoPreprocessors" => [
                    "Deinterlacer" => [
                        "Algorithm" => "INTERPOLATE",
                        "Mode" => "DEINTERLACE",
                        "Control" => "NORMAL"
                    ]
                ],
                "TimecodeInsertion" => "DISABLED",
                "AntiAlias" => "ENABLED",
                "Sharpness" => 50,
                "CodecSettings" => [
                    "Codec" => "H_264",
                    "H264Settings" => [
                        "InterlaceMode" => "PROGRESSIVE",
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 45,
                        "Slices" => 1,
                        "GopBReference" => "ENABLED",
                        "HrdBufferSize" => 800000,
                        "SlowPal" => "DISABLED",
                        "ParDenominator" => 1,
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "ENABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 400000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "MAIN",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 15000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "MEDIUM",
                        "CodecLevel" => "LEVEL_3_1",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling" => "NONE",
                "DropFrameTimecode" => "ENABLED",
                "RespondToAfd" => "NONE",
                "ColorMetadata" => "INSERT"
            ],
            "AudioDescriptions" => [
                [
                    "AudioTypeControl" => "FOLLOW_INPUT",
                    "AudioSourceName" => "Audio Selector 1",
                    "CodecSettings" => [
                        "Codec" => "AAC",
                        "AacSettings" => [
                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                            "Bitrate" => 64000,
                            "RateControlMode" => "CBR",
                            "CodecProfile" => "HEV1",
                            "CodingMode" => "CODING_MODE_2_0",
                            "RawFormat" => "NONE",
                            "SampleRate" => 48000,
                            "Specification" => "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl" => "FOLLOW_INPUT",
                    "AudioType" => 0
                ]
            ],

            "NameModifier" => "270p",
            "OutputSettings" => [
                "HlsSettings" => [
                    "SegmentModifier" => '$dt$'
                ]
            ]
        ];

        return $setting;
    }


    public function get144pSetting($params = array())
    {

        $orientation = (isset($params['orientation'])) ? trim($params['orientation']) : "landscape";
        $width      =  ($orientation == "landscape") ? 256 : 144;
        $height     =  ($orientation == "landscape") ? 144  : 256;

        //"Preset" => "Custom-Ott_Hls_Ts_Avc_Aac_16x9_256x144p_15Hz_0.4Mbps",

        $setting = [
            "ContainerSettings" => [
                "Container" => "M3U8",
                "M3u8Settings" => [
                    "AudioFramesPerPes" => 4,
                    "PcrControl" => "PCR_EVERY_PES_PACKET",
                    "PmtPid" => 480,
                    "PrivateMetadataPid" => 503,
                    "ProgramNumber" => 1,
                    "PatInterval" => 0,
                    "PmtInterval" => 0,
                    "VideoPid" => 481,
                    "AudioPids" => [
                        482,
                        483,
                        484,
                        485,
                        486,
                        487,
                        488,
                        489,
                        490,
                        491,
                        492,
                        493,
                        494,
                        495,
                        496,
                        497,
                        498
                    ]
                ]
            ],
            "VideoDescription" => [
                "Width" => 256,
                "ScalingBehavior" => "DEFAULT",
                "Height" => 144,
                "VideoPreprocessors" => [
                    "Deinterlacer" => [
                        "Algorithm" => "INTERPOLATE",
                        "Mode" => "DEINTERLACE",
                        "Control" => "NORMAL"
                    ]
                ],
                "TimecodeInsertion" => "DISABLED",
                "AntiAlias" => "ENABLED",
                "Sharpness" => 50,
                "CodecSettings" => [
                    "Codec" => "H_264",
                    "H264Settings" => [
                        "InterlaceMode" => "PROGRESSIVE",
                        "NumberReferenceFrames" => 3,
                        "Syntax" => "DEFAULT",
                        "FramerateDenominator" => 1001,
                        "GopClosedCadence" => 1,
                        "HrdBufferInitialFillPercentage" => 90,
                        "GopSize" => 45,
                        "Slices" => 1,
                        "GopBReference" => "ENABLED",
                        "HrdBufferSize" => 800000,
                        "SlowPal" => "DISABLED",
                        "ParDenominator" => 1,
                        "SpatialAdaptiveQuantization" => "ENABLED",
                        "TemporalAdaptiveQuantization" => "ENABLED",
                        "FlickerAdaptiveQuantization" => "ENABLED",
                        "EntropyEncoding" => "CABAC",
                        "Bitrate" => 400000,
                        "FramerateControl" => "SPECIFIED",
                        "RateControlMode" => "CBR",
                        "CodecProfile" => "MAIN",
                        "Telecine" => "NONE",
                        "FramerateNumerator" => 15000,
                        "MinIInterval" => 0,
                        "AdaptiveQuantization" => "MEDIUM",
                        "CodecLevel" => "LEVEL_3_1",
                        "FieldEncoding" => "PAFF",
                        "SceneChangeDetect" => "ENABLED",
                        "QualityTuningLevel" => "MULTI_PASS_HQ",
                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                        "UnregisteredSeiTimecode" => "DISABLED",
                        "GopSizeUnits" => "FRAMES",
                        "ParControl" => "SPECIFIED",
                        "NumberBFramesBetweenReferenceFrames" => 3,
                        "RepeatPps" => "DISABLED"
                    ]
                ],
                "AfdSignaling" => "NONE",
                "DropFrameTimecode" => "ENABLED",
                "RespondToAfd" => "NONE",
                "ColorMetadata" => "INSERT"
            ],
            "AudioDescriptions" => [
                [
                    "AudioTypeControl" => "FOLLOW_INPUT",
                    "AudioSourceName" => "Audio Selector 1",
                    "CodecSettings" => [
                        "Codec" => "AAC",
                        "AacSettings" => [
                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                            "Bitrate" => 64000,
                            "RateControlMode" => "CBR",
                            "CodecProfile" => "HEV1",
                            "CodingMode" => "CODING_MODE_2_0",
                            "RawFormat" => "NONE",
                            "SampleRate" => 48000,
                            "Specification" => "MPEG4"
                        ]
                    ],
                    "LanguageCodeControl" => "FOLLOW_INPUT",
                    "AudioType" => 0
                ]
            ],

            "NameModifier" => "144p",
            "OutputSettings" => [
                "HlsSettings" => [
                    "SegmentModifier" => '$dt$'
                ]
            ]
        ];

        return $setting;
    }




    public function getImageInserterSetting($params = array())
    {

        $ImageInserterSetting   =   [];
        $artist_id              =   (isset($params['artist_id']) && $params['artist_id'] != '') ? trim($params['artist_id']) : "";

        if($artist_id != ''){

            $objectpath     =   "watermark/$artist_id.png";
            $s3             =   Storage::disk('s3_armsrawvideos');
            $exists         =   $s3->has($objectpath);

            
            $ImageInserterSetting = [
                "InsertableImages" => [
                    [
                        "Width" => 150,
                        "ImageX" => 20,
                        "ImageY" => 200,
                        "Layer" => 0,
                        "ImageInserterInput" => "s3://armsvideos/watermark/$artist_id.png",
                        "Opacity"=> 50
                    ]
                ]
            ];
        }

        return $ImageInserterSetting;

    }


    public function sampleTranscode()
    {

        $jobSetting = [
            "OutputGroups" => [
                [
                    "Name" => "File Group",
                    "OutputGroupSettings" => [
                        "Type" => "FILE_GROUP_SETTINGS",
                        "FileGroupSettings" => [
                            "Destination" => "s3://armsvideos/testcmd/"
                        ]
                    ],
                    "Outputs" => [
                        [
                            "VideoDescription" => [
                                "ScalingBehavior" => "DEFAULT",
                                "TimecodeInsertion" => "DISABLED",
                                "AntiAlias" => "ENABLED",
                                "Sharpness" => 50,
                                "CodecSettings" => [
                                    "Codec" => "H_264",
                                    "H264Settings" => [
                                        "InterlaceMode" => "PROGRESSIVE",
                                        "NumberReferenceFrames" => 3,
                                        "Syntax" => "DEFAULT",
                                        "Softness" => 0,
                                        "GopClosedCadence" => 1,
                                        "GopSize" => 90,
                                        "Slices" => 1,
                                        "GopBReference" => "DISABLED",
                                        "SlowPal" => "DISABLED",
                                        "SpatialAdaptiveQuantization" => "ENABLED",
                                        "TemporalAdaptiveQuantization" => "ENABLED",
                                        "FlickerAdaptiveQuantization" => "DISABLED",
                                        "EntropyEncoding" => "CABAC",
                                        "Bitrate" => 5000000,
                                        "FramerateControl" => "SPECIFIED",
                                        "RateControlMode" => "CBR",
                                        "CodecProfile" => "MAIN",
                                        "Telecine" => "NONE",
                                        "MinIInterval" => 0,
                                        "AdaptiveQuantization" => "HIGH",
                                        "CodecLevel" => "AUTO",
                                        "FieldEncoding" => "PAFF",
                                        "SceneChangeDetect" => "ENABLED",
                                        "QualityTuningLevel" => "SINGLE_PASS",
                                        "FramerateConversionAlgorithm" => "DUPLICATE_DROP",
                                        "UnregisteredSeiTimecode" => "DISABLED",
                                        "GopSizeUnits" => "FRAMES",
                                        "ParControl" => "SPECIFIED",
                                        "NumberBFramesBetweenReferenceFrames" => 2,
                                        "RepeatPps" => "DISABLED",
                                        "FramerateNumerator" => 30,
                                        "FramerateDenominator" => 1,

                                        "ParDenominator" => 1
                                    ]
                                ],
                                "AfdSignaling" => "NONE",
                                "DropFrameTimecode" => "ENABLED",
                                "RespondToAfd" => "NONE",
                                "ColorMetadata" => "INSERT"
                            ],
                            "AudioDescriptions" => [
                                [
                                    "AudioTypeControl" => "FOLLOW_INPUT",
                                    "CodecSettings" => [
                                        "Codec" => "AAC",
                                        "AacSettings" => [
                                            "AudioDescriptionBroadcasterMix" => "NORMAL",
                                            "RateControlMode" => "CBR",
                                            "CodecProfile" => "LC",
                                            "CodingMode" => "CODING_MODE_2_0",
                                            "RawFormat" => "NONE",
                                            "SampleRate" => 48000,
                                            "Specification" => "MPEG4",
                                            "Bitrate" => 64000
                                        ]
                                    ],
                                    "LanguageCodeControl" => "FOLLOW_INPUT",
                                    "AudioSourceName" => "Audio Selector 1"
                                ]
                            ],
                            "ContainerSettings" => [
                                "Container" => "MP4",
                                "Mp4Settings" => [
                                    "CslgAtom" => "INCLUDE",
                                    "FreeSpaceBox" => "EXCLUDE",
                                    "MoovPlacement" => "PROGRESSIVE_DOWNLOAD"
                                ]
                            ],
                            "NameModifier" => "_1"
                        ]
                    ]
                ]
            ],
            "AdAvailOffset" => 0,
            "Inputs" => [
                [
                    "AudioSelectors" => [
                        "Audio Selector 1" => [
                            "Offset" => 0,
                            "DefaultSelection" => "NOT_DEFAULT",
                            "ProgramSelection" => 1,
                            "SelectorType" => "TRACK",
                            "Tracks" => [
                                1
                            ]
                        ]
                    ],
                    "VideoSelector" => [
                        "ColorSpace" => "FOLLOW"
                    ],
                    "FilterEnable" => "AUTO",
                    "PsiControl" => "USE_PSI",
                    "FilterStrength" => 0,
                    "DeblockFilter" => "DISABLED",
                    "DenoiseFilter" => "DISABLED",
                    "TimecodeSource" => "EMBEDDED",
                    "FileInput" => "s3://armsrawvideos/1532776693_tmpphp8xgz38.mp4"
                ]
            ],
            "TimecodeConfig" => [
                "Source" => "EMBEDDED"
            ]
        ];

        try {
            $result = $this->mediaConvertClient->createJob([
                "Role" => "arn:aws:iam::337477240173:role/mediaconvert",
                "Settings" => $jobSetting, //JobSettings structure
                "Queue" => "arn:aws:mediaconvert:ap-southeast-1:337477240173:queues/Default",
                "UserMetadata" => [
                    "Customer" => "Amazon"
                ],
            ]);

        } catch (AwsException $e) {
            echo $e->getMessage();
            echo "\n";
        }


    }


    public function getJobStatus($jobid)
    {
        $error_messages = $results = [];

        try {

            $jobStatusRequest = $this->mediaConvertClient->getJob(['Id' => $jobid]);
            $jobData = $jobStatusRequest->get('Job');
            $job_id = $jobData['Id'];
            $job_status = strtolower($jobData['Status']);

            $results = [
                'job_data' => $jobData,
                'job_id' => $job_id,
                'job_status' => $job_status,
                'error' => false
            ];

        } catch (AwsException $e) {
            $error_messages = ['error' => true, 'type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()];
            Log::info('MediaConvert - createHlsVodJob  : Fail ', $error_messages);
        }

        return ['error_messages' => $error_messages, 'results' => $results];
    }


}