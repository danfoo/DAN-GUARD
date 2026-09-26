package com.danguard.lock

import com.google.gson.annotations.SerializedName
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.Header
import retrofit2.http.POST
import java.util.concurrent.TimeUnit

/* -------- Modèles -------- */

data class EnrollRequest(
    @SerializedName("enrollment_token") val enrollmentToken: String,
    val imei: String?,
    val serial: String?,
    @SerializedName("android_id") val androidId: String?,
    val model: String?,
    @SerializedName("fcm_token") val fcmToken: String?
)

data class EnrollResponse(
    @SerializedName("device_id") val deviceId: Int,
    @SerializedName("api_token") val apiToken: String,
    @SerializedName("checkin_interval") val checkinInterval: Int?
)

data class CheckinRequest(
    val status: String,
    @SerializedName("fcm_token") val fcmToken: String? = null
)

data class Command(
    val id: Int,
    val command: String,
    val payload: Map<String, Any?>?
)

data class CheckinResponse(
    @SerializedName("device_id") val deviceId: Int,
    val status: String,
    @SerializedName("should_lock") val shouldLock: Boolean,
    @SerializedName("lock_message") val lockMessage: String?,
    @SerializedName("checkin_interval") val checkinInterval: Int?,
    val commands: List<Command>?
)

data class AckRequest(@SerializedName("command_id") val commandId: Int)

/* -------- Service -------- */

interface DanGuardApi {

    @POST("enroll")
    suspend fun enroll(@Body body: EnrollRequest): EnrollResponse

    @POST("checkin")
    suspend fun checkin(
        @Header("Authorization") bearer: String,
        @Body body: CheckinRequest
    ): CheckinResponse

    @POST("ack")
    suspend fun ack(
        @Header("Authorization") bearer: String,
        @Body body: AckRequest
    )
}

object ApiFactory {
    fun create(baseUrl: String = BuildConfig.PERFEX_BASE_URL): DanGuardApi {
        val client = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .build()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(DanGuardApi::class.java)
    }
}
